<?php

require_once "db.php";
require_once "common.php";

$web_user = isset($_SERVER["REMOTE_USER"]) ? $_SERVER["REMOTE_USER"] : "";

if( !defined('ORGANIZE_RESERVATIONS_BY_FLOOR') || ORGANIZE_RESERVATIONS_BY_FLOOR ) {
  $organize_reservations_by_floor = true;
} else {
  $organize_reservations_by_floor = false;
}

function matchRoom($filter_room_regex,$rooms) {
  if( !$filter_room_regex || !count($rooms) ) {
    return true;
  }

  $matched = false;
  foreach( $rooms as $room ) {
    if( preg_match($filter_room_regex,$room) ) {
      $matched = true;
      break;
    }
  }
  return $matched;
}

$building_sql = '';
if( isset($_REQUEST['building']) && $_REQUEST['building'] ) {
  $building_sql = 'AND BUILDING = :BUILDING';
}

$dbh = connectDB();
$sql = "SELECT * FROM building_access WHERE START_TIME < :END_TIME AND END_TIME > :START_TIME AND (APPROVED NOT IN ('N','" . INITIALIZING_APPROVAL . "') OR NETID = :NETID) {$building_sql} ORDER BY START_TIME,REQUESTED";
$stmt = $dbh->prepare($sql);

$stmt->bindValue(":NETID",$web_user);
if( $building_sql ) {
  $stmt->bindValue(":BUILDING",$_REQUEST["building"]);
}

$cur_day = $_REQUEST["day"];
$slot_minutes = $_REQUEST["slot_minutes"];
$filter_room = isset($_REQUEST["room"]) ? $_REQUEST["room"] : "";
$filter_room = canonicalRoomList($filter_room);

$filter_room_regex = "";
foreach( preg_split("{ *, *}",$filter_room) as $room ) {
  if( !$room ) continue;
  if( $filter_room_regex ) $filter_room_regex .= '|';
  $filter_room_regex .= $room;
}
if( $filter_room_regex ) {
  $filter_room_regex = "{^(" . $filter_room_regex . ")}i";
}

$min_hour = 0;
$max_hour = 24;
if( isset($_REQUEST['start_time']) && preg_match('{([0-9]+):([0-9]+)}',$_REQUEST['start_time'],$match) ) {
  $min_hour = (int)$match[1];
  if( $min_hour ) $min_hour -= 1; # include previous hour
}
if( isset($_REQUEST['end_time']) && preg_match('{([0-9]+):([0-9]+)}',$_REQUEST['end_time'],$match) ) {
  $max_hour = (int)$match[1];
  if( $max_hour < 24 ) $max_hour += 1; # include following hour
}

$results = array();
for( $hour=0; $hour < 24; $hour++ ) {
  for( $minute = 0; $minute < 60; $minute += $slot_minutes ) {
    $slotinfo = "";
    $slotinfo_floor = array();
    $vname = "slot_{$hour}_{$minute}";
    if( $hour < $min_hour || $hour > $max_hour ) {
      # return empty result for this slot so front end will clear it
      $results[$vname] = "hide";
      continue;
    }

    $start_time = "{$cur_day} {$hour}:{$minute}";
    $end_time = date("Y-m-d H:i",strtotime($start_time)+$slot_minutes*60);
    $stmt->bindValue(":START_TIME",$start_time);
    $stmt->bindValue(":END_TIME",$end_time);
    $stmt->execute();

    # group together adjacent reservations (as produced by the original slot-based request form)
    $usage_entries = array();
    while( ($row=$stmt->fetch()) ) {
      $done = false;
      foreach( $usage_entries as &$usage_entry ) {
        if( $row["NETID"] == $usage_entry["NETID"] && $row["ROOM"] == $usage_entry["ROOM"] && $row["BUILDING"] == $usage_entry["BUILDING"] && $row["PURPOSE"] == $usage_entry["PURPOSE"] )
        {
          if( $row["START_TIME"] == $usage_entry["END_TIME"] ) {
            $usage_entry["END_TIME"] = $row["END_TIME"];
            $done = true;
            break;
          }
        }
      }
      if( !$done ) {
        $usage_entries[] = $row;
      }
    }

    foreach( $usage_entries as $row ) {
      $purpose = $row["PURPOSE"];
      $timespan = date("H:i",strtotime($row["START_TIME"])) . "-" . date("H:i",strtotime($row["END_TIME"]));
      $approved = $row["APPROVED"];
      if($approved == 'N') {
        $pending_approval = "approval_denied";
        $status = "not aproved";
      } else if( $approved == 'Y' ) {
        $pending_approval = "";
        $status = "";
      } else if( $approved == INITIALIZING_APPROVAL ) {
        $pending_approval = "approval_incomplete";
        $status = "incomplete submission";
      } else {
        $pending_approval = "pending_approval";
        $status = "pending approval";
      }
      $extra_info = $status;
      if( $extra_info ) $extra_info .= ": ";
      $extra_info .= $timespan;
      if( $extra_info ) $extra_info .= " ";
      $extra_info .= $purpose;

      $rooms = explode(", ",$row['ROOM']);
      $building = buildingAbbreviation($row["BUILDING"]);
      if( $row["NETID"] == $web_user ) {
        $edit = "<a href='?id=" . htmlescape($row["ID"]) . "'><i class='far fa-edit'></i></a>";
      } else {
        $edit = "";
      }

      if( !$organize_reservations_by_floor ) {
        if( !matchRoom($filter_room_regex,$rooms) ) continue;
        $slotinfo .= "<span class='usage-entry $pending_approval' title='" . htmlescape($extra_info) . "'>$edit" . htmlescape($row["NAME"] . " " . $building . " " . $row["ROOM"]) . "</span> ";
      } else {
        $floors_done = array();
        foreach( $rooms as $room ) {
	  $floor = getFloor($room);
	  if( array_key_exists($floor,$floors_done) ) continue;
	  $floors_done[$floor] = 1;

          $this_floor_rooms = array();
          foreach( $rooms as $this_floor_room ) {
	    if( getFloor($this_floor_room) != $floor ) continue;
	    $this_floor_rooms[] = $this_floor_room;
	  }

          if( !matchRoom($filter_room_regex,$this_floor_rooms) ) continue;
	  $this_floor_rooms = implode(",",$this_floor_rooms);
          $this_slotinfo = "<span class='usage-entry $pending_approval' title='" . htmlescape($extra_info) . "'>$edit" . htmlescape($row["NAME"] . " " . $building . " " . $this_floor_rooms) . "</span> ";

	  if( !array_key_exists($floor,$slotinfo_floor) ) {
	    $slotinfo_floor[$floor] = array();
	  }
	  if( !array_key_exists($room,$slotinfo_floor[$floor]) ) {
	    $slotinfo_floor[$floor][$room] = array();
	  }
	  $slotinfo_floor[$floor][$room][] = $this_slotinfo;
	}
      }
    }

    if( $organize_reservations_by_floor ) {
      uksort($slotinfo_floor,'compare_floors');
      foreach( $slotinfo_floor as $floor ) {
        $slotinfo .= "<div class='floor-usage-entry'>";
        uksort($floor,'compare_rooms');
        foreach( $floor as $room ) {
	  foreach( $room as $this_slotinfo ) {
	    $slotinfo .= $this_slotinfo;
	  }
	}
        $slotinfo .= "</div>\n";
      }
    }

    $results[$vname] = $slotinfo;
  }
}
echo json_encode($results);
