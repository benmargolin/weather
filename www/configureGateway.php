<?php

//
// LacrossE Gateway Tool  mike@mycal.net
//

function byteStr2byteArray($s) {
  return array_slice(unpack("C*", "\0".$s), 1);
}

function hex_dump($data, $newline="\n")
{
  static $from = '';
  static $to = '';

  static $width = 16; # number of bytes per line

  static $pad = '.'; # padding for non-visible characters

  if ($from==='')
  {
    for ($i=0; $i<=0xFF; $i++)
    {
      $from .= chr($i);
      $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
    }
  }

  $hex = str_split(bin2hex($data), $width*2);
  $chars = str_split(strtr($data, $from, $to), $width);

  $offset = 0;
  foreach ($hex as $i => $line)
  {
    echo sprintf('%6X',$offset).' : '.implode(' ', str_split($line,2));
    echo str_repeat('   ', $width - strlen($line) / 2);
    echo ' [' . $chars[$i] . ']' . $newline;
    $offset += $width;
  }
}


function send_broadcast_packet_to_gateway($socket,$port,$packet_type,$mac_addr,$payload)
{
    // create the request packet
    $packet = $packet_type . $mac_addr . $payload;
    // Send the packet
    socket_sendto($socket, $packet, strlen($packet), 0, '255.255.255.255', $port);
}


$src_port=61751;
$dst_port=8003;

$packet_type=chr(0).chr(1);                                                     // 00 01 search
$mac_addr=chr(0).chr(0).chr(0).chr(0).chr(0).chr(0);
//$mac_addr=chr(0);
$payload=chr(0).chr(0xa);
$output="";

$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1); 


// Bind a port for receve of packets from gateway
if(socket_bind($socket,0,$src_port))
{
  echo "<pre>";
  echo("Ready to receive.\n");
  
  socket_set_nonblock ( $socket );

  send_broadcast_packet_to_gateway($socket,$dst_port,$packet_type,$mac_addr,$payload);
  
  $q=30000;
 
  while($q)
  {
    if(@socket_recvfrom($socket, $buffer, 1024, 0, $from, $port))
    {

      echo "Received packet from remote address $from and remote port $port" . PHP_EOL;

      hex_dump($buffer);
      break;
    }
    else
    {
        usleep(100);
    }
    $q--;
  }

  echo "</pre>";
}


  
?>

