<?php
/*

@author mike |at| mycal.net
@author karlc |at| keckec.com

Weather Station Server. This is expermental code to enable registering and
receiving ongoing data feeds from a Lacrosse Technologies GW-1000U gateway
and a Lacrosse Technologies C84612 weather station. 

You should read this thread on the wxfourm about this and skydivers software:
http://www.wxforum.net/index.php?topic=14299.75

The idea behind this code as oppsed dto skydivers great software, it it
could be adapted to a home router using DDWRT or hosted on a cheap host
that supports PHP, and is in general not bound to Windows.

All doucmentation is here in this single file.

One word of warning, you should register your gateway and weather station 
to the lacrosse servers FIRST if you ever wish to use them in the future.
Once they have been initally registered you should be able to use this
software, skydivers software or the lacrosse alerts service without problems
and switch at any time between them.

This software emulates the Lacrosse Alerts api on their server with the 
URI location of /request.breq.  You must set up your server to 
resolve .breq files as PHP files to use this extention as a PHP file.
This can be done by adding a line into your .htaccess file:

AddHandler php5-script .php .breq

Once this is set up, you will also need to set the gateway to use your
server.  The simplest way it to set your server as the "proxy" using the 
Gateway Advance Setup (GAS) utility available from Lacrosse.

Requests all goto the URI /request.breq

Each request has a special HTTP request header called HTTP_IDENTIFY,
which specifies the the request type, identification of the gateway, 
and the gateway KEY.


HTTP_IDENTIFY: 8009A427:01:1ACD476DFAC43232:01
               ^^  ^^   ^^        ^^        ^^
               A   B    C         D         E

This is from my (KEC) capture
HTTP_IDENTIFY: 80097B29:00:914C428A9FD46E58:70

Each packet types seems to consist of:

A= 80, always 80 so far (2 chars).
B= MAC address less vendor ID (6 chars)
C= Packet Code 1 (2 chars)
D= RegistrationCode or Device Serial Number or other Identifier (16 chars)
E= Packet Code (2 chars)

From here on in, each packet type will be called by C:E or as in 
the above example packet (01:01)

Each request may or may not have HTTP POST data.

Each reply will also have a corresponding HTTP_FLAGS header
which will only be the 2 return codes like so:

HTTP_FLAGS : 00:00

Each request has a corresponding return code placed in the 
HTTP_FLAGS header, and the response may or may not have data.

Packets
-------
00:01 - Gateway Power up Packet (when gateway is unregistered), 
        reply with empty message (HTTP_FLAGS: 10:00)
00:10 -
00:20 - Gateway Unregistered Push Button Packet, you can respond 
        to this message with a gateway config response (HTTP_FLAGS: 20:00)
00:30 - Gateway finished registering packet, respond with (HTTP_FLAGS: 30:00)
00:70 - Gateway Ping Packet (nothing to do with weather station, 
        just keeps the gateway happy)
01:00 - Weather Station Ping. Sets time, lights internet light on station;
        Reply with header(HTTP_FLAGS: 14:01)
01:01 - Weather Station Data packet, this contains the weather station data.
01:14 - Weather Station Registrion verification packet
7F:10 - Weather Station Registration Packet
00:14 - 


Data Packet layout
------------------
The data is sent from the gateway as post data in a 01:01 record.
The records are 197 bytes, although users have reported other-sized
packets too. That will need investigation.
The layout of the 197-byte record is as follows. Start and end byte numbers
end with H or L to indicate which nybble the field starts or ends with. 
H or L indicate the high-order or low-order nybble. 
The length is given in nybbles, not bytes.
----------------------------------
    |Strt|Len in|
Strt|nyb |nybble|Encoding |Function
----------------------------------
00H   0    2      byte     Record type, always 01
01H   2    4      ???      Unknown
03H   6    3      byte     status?
04L   9    10     BDC      Date/Time of Max Inside Temp
09L   13   10     BCD      Date/Time of Min Inside Temp
0eL   1d   3      BCD      Max Inside Temp
10H   20   2      ???      Unknown
11H   22   3      BCD      Min Inside Temp
12L   25   2      ???      Unknown
13L   27   3      BCD      Current Inside Temp
15H   2a   3      ???      Unknown
16L   2d   10     BCD      Date/Time of Max Outside Temp
1bL   37   10     BCD      Date/Time of Min Outside Temp
20L   41   3      BCD      Max Outside Temp
22H   44   2      ???      Unknown
23H   46   3      BCD      Min Outside Temp
24L   49   2      ???      Unknown
25L   4b   3      BCD      Current Outside Temp
27H   4e   3      ???      Unknown
28L   51   10     BCD      Unknown Date/Time 1
2dL   5b   10     BCD      Unknown Date/Time 2
32L   65   10     ???      Unknown
37L   6f   3      BCD      Second copy of outside temp(?)
39H   72   2      ???      Status byte – per skydvr 0xA0 – error
3aH   74   10     BCD      Date/Time of Max Inside Humidity
3fH   7e   10     BCD      Date/Time of Min Inside Humidity
44H   88   2      binary   Max Inside Humidity
45H   8a   2      binary   Min Inside Humidity
46H   8c   2      binary   Current Inside Humidity
47H   8e   10     BCD      Date/Time of Max Outside Humidity
4cH   98   10     BCD      Date/Time of Min Outside Humidity
51H   a2   2      binary   Max Outside Humidity
52H   a4   2      binary   Min Outside Humidity
53H   a6   2      binary   Current Outside Humidity
54H   a8   18     ???      Unknown all 0s
5dH   ba   4      ???      Unknown
5fH   be   20     ???      Unknown all 0s
69H   d2   2      ???      Unknown
6aH   d4   10     BCD      Unknown Date/Time 3
6fH   de   12     ???      Unknown
75H   ea   10     BCD      Date/Time last 1-hour rain window ended
7aH   f4   13     ???      Unknown
80L   101  10     BCD      Date/Time of Last Rain Reset
85L   10b  23     ???      Unknown – skydvr says rainfall array
91H   122  4      binary   Current Ave Wind Speed
93H   126  4      ???      Unknown
95H   12a  6      nybbles  Wind direction history -- One nybble per time period
98H   130  10     BCD      Time of Max Wind Gust
9dH   13a  4      binary   Max Wind Gust since reset in 100th of km/h
9fH   13e  2      ???      Unknown
a0H   140  4      binary   Max Wind Gust this Cycle in 100th of km/h
a2H   144  4      ???      Unknown – skydvr says wind status
a4H   148  6      nybbles  Second copy of wind direction history?
a7H   14e  1      ???      Unknown
a7L   14f  4      BCD      Current barometer in inches Hg
a9L   153  6      ???      Unknown – skydvr says 0xAA might be pressure delta
acL   159  4      BCD      Min Barometer
aeL   15d  6      ???      Unknown
b1L   163  4      BCD      Max Barometer
b3L   167  5      ???      Unknown
b6H   16c  10     BCD      Unknown Date/Time 5
bbH   176  10     BCD      Unknown Date/Time 6
c0H   180  6      ???      Unknown
c3H   186  2      binary   Checksum1
c4H   188  2      binary   Checksum2 May be one 16-bit checksum

Data Field Notes:
1. Date/Time fields are BCD spanning 10 nybbles. It starts with the 2-digit 
   year (without century), then 2-digit month, then 2-digit day, then 2-digit 
   hour, then 2-digit minute.
2. Barometer is BCD in hundredths of an inch of mercury.
3. Wind speed and gust are in hundredths of km/h
4. Humidity is one byte, in percent
5. Temperatures are in hundredths of a degree C, plus 40 degrees C


Registration Gateway
-------------------- 
Registration takes place in 2 parts, Gateway registration and 
Weather Station registration. 
Gateway registration is straightforward and should pose no issues to
reregister as many times as you like.  You can reset a gateway to 
default by pressing and holding the button while powering up the gateway.
It can then be re-registered.

After Registration, the Gateway will ping the service with a 00:70
every so often, this frequency is set in the reply to this packet.
Typically 240 seconds.

Registration Weather Station
----------------------------

Two types of registration a new registration and a re-registration.   A new
registration will set a serial number in the weather station, so I recommend
you do the first registraton with Lacrosse alerts to get a "good" serial
number written.  After that you can  re-register using this software, and if
you ever want to go back you can.

Pressing the Rain button on the weather station should have it blink "REG",
then push the gateway button.  A 7F:10 -  Weather Station Registration Packet
should be generated.  This will include what the weather station thinks its
serial number is, if starts with 7FFF then it has been registered before, and
you must reply with this address.  If 0102030405060708 then it has not been
registered before, and whatever serial number you reply with will be written
to the weather station.

Once this packet has been received by the weather station a 01:14 packet will
be sent with the new serial number (or old one if reregistered), and once this
is replied to the weather station is registered.

The weather station will then send 01:01 data packets and 01:00 ping packets
to the service. The ping packets must be responded to correctly to keep the 
"internet" indicator up and to keep the weather station "registered" over the
long term.

To Investigate
---------------
I think on the lacrosse alerts website you can change the rate of data packets,
we should change this setting and see what happens in these packets. 
I thought at one point I could change this, but I've lost that data.

Lastly
------
Pressing the RAIN button until it beeps on a registered weather station
flushes out data packets from the weather station.

*/


//----------------------------------------------------------------------------------------------------------
// Configuration, customize for your situation
//----------------------------------------------------------------------------------------------------------
date_default_timezone_set('America/Los_Angeles');   // We use this to set the correct time on your weather station
//$station_serial=pack("H*" , "7FFF000000000000");    // Set your station serial number here
//$station_serial=pack("H*" , "914C428A9FD46E58");    // keckec serial number This is wrong!!!!
$station_serial=pack("H*" , "7fff14763c3b5b61");    // keckec serial number apparently something else, this one is seen 
$register_this_serial=0;                            // Warning, read the code before setting this to 1, will set to above sn
                                                    // if a weather station with default serial number attaches, you probably
                                                    // do not want to do this.

// The unknown entities is an array of arrays. The inner arrays
//  each have 2 elements -- first is the starting nybble in the post data
//  and the second is the length in nybbles
$unknown_entities = array(
				array(0x002,4),
				array(0x006,3),
				array(0x020,2),
				array(0x025,2),
				array(0x02a,3),
				array(0x044,2),
				array(0x049,2),
				array(0x04e,3),
				array(0x065,10),
				array(0x072,2),
				array(0x0a8,16),
				array(0x0bf,15),
				array(0x0de,6),
				array(0x0f4,6),
				array(0x100,1),
				array(0x11c,6),
				array(0x126,4),
				array(0x13e,2),
				array(0x144,4),
				array(0x14e,1),
				array(0x153,6),
				array(0x15d,6),
				array(0x167,5),
				array(0x180,6),
);

//
// Writes the given string to the data log file: php.log
//
function write_data_log($outstr)
{
	file_put_contents("php.log",$outstr,FILE_APPEND);
}

//
// Writes the given string to the bcd log file: bcd.log
//
function write_bdc_log($outstr)
{
	file_put_contents("bcd.log",$outstr,FILE_APPEND);
}

//
// Writes the given string to the dump log file: dump.log
//
function write_dump_log($data,$type)
{
	$outstr=date('Y-m-d H:i:s')."   Packet Type ".$type;
	$outstr.= sprintf(" Length: %d bytes\n",strlen($data));
	if(strlen($data))
	{
		$outstr.= hex_dump($data)."\n";
	} else {
		$outstr .= "     No Data\n";
	}
	file_put_contents("dump.log",$outstr,FILE_APPEND);
}

//
// post2nyb converts the string post data into an array of nybbles,
//  one for each nybble in the ord values of the original string.
//
function post2nyb($postdata)
{
	for ($i = 0; $i < strlen($postdata); $i++)
	{
		$ary[] = ord($postdata[$i]) >> 4;
		$ary[] = ord($postdata[$i]) & 0xF;
	}
	return $ary;
}

//
// dump_unknown displays bytes in the post data that are in the 
//  $unknown_entities global. This is displayed as a hex dump, with
//  only the nybbles in the unknown_entities array are shown, with
//  the others being blank. As we identify more blocks of data, the
//  array should shrink.
//
function dump_unknown($instr)
{
	$nybs_per_line = 64;
	// Convert the post data to an array of nybbles
	$nybary = post2nyb($instr);
	global $unknown_entities;
	$cnt = 0;
	$outstr =  "       ";
	// Display the headings in two lines, one for the
	// first hex digit and one for the second
	for ($i = 0; $i < $nybs_per_line; $i++) 
		$outstr .= sprintf("|%01x",(int)($i/16));
	$outstr .= "\n";
	$outstr .= "by nyb ";
	for ($i = 0; $i < $nybs_per_line; $i++) 
		$outstr .= sprintf("|%01x",$i%16);
	$i = 0;
	foreach ($unknown_entities as $startlen)
	{
		while ($i < $startlen[0])
		{
			// Known bytes
			if (($i % $nybs_per_line) == 0)
				$outstr .= sprintf("\n%02x %03x ",$i/2,$i);
			$outstr .= "| ";
			$i++;
		}

		while ($i < ($startlen[0] + $startlen[1]))
		{
			// Unknown bytes
			if (($i % $nybs_per_line) == 0)
				$outstr .= sprintf("\n%02x %03x ",$i/2,$i);
			$outstr .= sprintf("|%x",$nybary[$i]);
			$i++;
		}
		$cnt+=$startlen[1];
	}
	$outstr .= sprintf("\n        %d out of %d nybbles are unknown",$cnt,count($nybary));
	$outstr .= "\n";
	return $outstr;
}

//
// Returns a string of a hex dump of the data given.
//
function hex_dump($instr)
{
	$bytes_per_line = 32;
	if(strlen($instr) < $bytes_per_line)
		$bytes_per_line = strlen($instr);
	$outstr = "     ";
	for ($i = 0; $i < $bytes_per_line; $i++) 
		$outstr .= sprintf("|%02x",$i);
	for ($i = 0;$i < strlen($instr); $i++)
	{
		if (($i % $bytes_per_line) == 0)
			$outstr .= sprintf("\n%02x - ",$i);
		$outstr .= sprintf("|%02x",ord($instr[$i]));
	}
	return $outstr;
}

//
// Convert the $value into a binary coded decimal, some configuration variables are BCD
//
function bin2bcd($value)
{
    if(is_string($value))
        $value=intval($value);

    $msb=$value/10;
    $lsb=$value%10;
    if($msb>10)
        $msb=10;
    return(($msb<<4)|($lsb&0xf));
}

//
// bcd2int converts BCD nybbles to an int.
//  $startn specifies the starting nybble (0-based), and $len
//  specifies how many nybbles to use.
//
function bcd2int(&$nybs,$startn,$len)
{
	$retval = 0;
	for ($i = 0; $i < $len; $i++)
		$retval = $retval * 10 + $nybs[$startn+$i];
	return $retval;
}

//
// bin2int converts binary nybbles to an integer, given the starting nybble
//  and number of nybbles to use. This is the same function as bcd2int
//  above, but shifts the result by 4 bits for each nybble processed rather than
//  multiplying by 10.
//
function bin2int(&$nybs,$startn,$len)
{
	$retval = 0;
	for ($i = 0; $i < $len; $i++)
		$retval = ($retval << 4) + $nybs[$startn+$i];
	return $retval;
}

//
// rf2str converts from the nybs array to a string in terms of total rainfall.  
// Rainfall is given as a BCD value in either 6 or 7 nybbles, as given by the 
// $len argument.
//
// Rain is measured in a non-obvious way. Each tip of the bucket is 
// approximately 0.01" of rain. But the Lacrosse console seems to consider the 
// first tip to be possibly bogus. One tip of the bucket shows the umbrella on 
// the display, but doesn't appear to change the displayed rain values. There 
// is always the possibility that after the last rain the bucket is almost full 
// and ready to tip, and even one drop after that could be recorded as 0.01".  
// Even a bird could land on the gage and cause a tip. When the console 
// receives a second tip , it displays 0.02". No doubt it has to be within a 
// certain amount of time, maybe 1 hour. However the post data output tallies 
// every tip. 
//
// Rain amounts in the post data are given either as 6- or 7-nybble values. The 
// values for hour, 24-hour, and 1 week are 6-nybble values, in 0.01mm 
// increments. Values for 1 month and the total are 7-nybbles wide, in 0.001mm 
// increments. However, it appears the values are actually converted from 
// hundredths of an inch, to millimeters, but by a value not quite 25.4mm/in, 
// but 25.516mm/inch. This is probably some calibration value for manufacturing 
// inaccuracies in the bucket and funnel.
//
function rf2str(&$nybs,$startn,$len)
{
	// If the rain is in 6 nybbles, it's in .01mm increments
	// If it is in 7 nybbles, it's in 0.001mm increments
	$rf = bcd2int($nybs,$startn,$len);
	$rf /= ($len == 6) ? 100.0 : 1000.0;
	$rf *= 0.0391904; // 0.0393701 is nominal
	return sprintf("%.2f",$rf);
}

//
// ws2str converts from the nybs array to a string in terms of 
//  wind speed. Wind speed is given as a binary value in four nybbles. 
//  It is in 100ths of km/h. To convert to mph we divide by 160.93.
// 160.93 seems to be a bit low to agree with the console display. 
//
function ws2str(&$nybs,$startn)
{
	// Windspeed is a two-byte value, high-byte first
	$ws = bin2int($nybs,$startn,4);
	$ws /= 161.9;
	return sprintf("%5.2f",$ws);
}

//
// wd2str converts 6 nybbles to string in terms of wind direction history. The 
// direction history is a BCD array, with the first nybble containing the 
// newest direction. The direction is in 22.5deg increments, starting with 0 as north 
//  and going to NNE as the first 22.5deg increment. There are 6 nybbles in the 
//  array, with the left-most one showing the current direction, and 5 older 
//  directions
//
function wd2str(&$nybs,$startn)
{
	$compass = array ("N  ","NNE","NE ","ENE","E  ","ESE","SE ","SSE","S  ",
		"SSW","SW ","WSW","W  ","WNW","NW ","NNW");
	$retstr = "";
	$retstr .= $compass[$nybs[$startn]]." "; // Current direction
	$retstr .= $compass[$nybs[$startn+1]]." "; // History 1
	$retstr .= $compass[$nybs[$startn+2]]." "; // History 2
	$retstr .= $compass[$nybs[$startn+3]]." "; // History 3
	$retstr .= $compass[$nybs[$startn+4]]." "; // History 4
	$retstr .= $compass[$nybs[$startn+5]]; // History 5
	return $retstr;
}

//
// dat2str converts nybbles into a date, formatted
//  YY/MM/DD-hh:mm, starting at the specified 
//  and the specified nybble offset.
// There are no seconds here
//
function dat2str(&$nybs,$startn)
{
	$yy = bcd2int($nybs,$startn+0,2);
	$mm = bcd2int($nybs,$startn+2,2);
	$dd = bcd2int($nybs,$startn+4,2);
	$hh = bcd2int($nybs,$startn+6,2);
	$min = bcd2int($nybs,$startn+8,2);
	return sprintf("%02d/%02d/%02d-%02d:%02d",$mm,$dd,$yy,$hh,$min);
}


//
// baro2str converts an array of nybbles to barometric
//  pressure in inches of mercury.
//
function baro2str(&$nybs,$startn)
{
	$retstr = "";
	//$retstr .= sprintf("%02x",ord($instr[0])) . " " . sprintf("%02x",ord($instr[1])) . " " . sprintf("%02x",ord($instr[2])) . " ";
	// The barometric pressure is in four nybbles, starting with the low nybble of the first
	//  byte of the string, and not including the low nybble of the last byte.
	//  nybble of the first byte as tenths.
	$baro = bcd2int($nybs,$startn,4) / 100.0;
	$retstr .= sprintf("%.2f",$baro);
	return $retstr;
}

//
// temp2str converts the array of nybbles into a string in terms of 
//  degrees C or F. The starting offset in nybbles starting nybble
//  (either 0 or 1) are given.
// The temperature value is bcd, in tenths of a degree C, offset by 40.0 
//  degress C. This avoids having to deal with negative numbers
//
function temp2str(&$nybs,$startn)
{
	$retstr = "";
	$otemp = bcd2int($nybs,$startn,3) / 10.0 - 40.0;
	$otemp = $otemp * 9.0 / 5 + 32.0;
	$retstr .= sprintf("%6.2f",$otemp);
	return $retstr;
}

//
// hum2str converts the given part of the nybble array to a string of 
//  humidity. Humidity is 2 nybbles
//
function hum2str(&$nybs,$startn)
{
	return bcd2int($nybs,$startn,2);
}

// For polynomial 0x1021
$crc16_table = array(
    0x0000, 0x1189, 0x2312, 0x329b, 0x4624, 0x57ad, 0x6536, 0x74bf,
    0x8c48, 0x9dc1, 0xaf5a, 0xbed3, 0xca6c, 0xdbe5, 0xe97e, 0xf8f7,
    0x1081, 0x0108, 0x3393, 0x221a, 0x56a5, 0x472c, 0x75b7, 0x643e,
    0x9cc9, 0x8d40, 0xbfdb, 0xae52, 0xdaed, 0xcb64, 0xf9ff, 0xe876,
    0x2102, 0x308b, 0x0210, 0x1399, 0x6726, 0x76af, 0x4434, 0x55bd,
    0xad4a, 0xbcc3, 0x8e58, 0x9fd1, 0xeb6e, 0xfae7, 0xc87c, 0xd9f5,
    0x3183, 0x200a, 0x1291, 0x0318, 0x77a7, 0x662e, 0x54b5, 0x453c,
    0xbdcb, 0xac42, 0x9ed9, 0x8f50, 0xfbef, 0xea66, 0xd8fd, 0xc974,
    0x4204, 0x538d, 0x6116, 0x709f, 0x0420, 0x15a9, 0x2732, 0x36bb,
    0xce4c, 0xdfc5, 0xed5e, 0xfcd7, 0x8868, 0x99e1, 0xab7a, 0xbaf3,
    0x5285, 0x430c, 0x7197, 0x601e, 0x14a1, 0x0528, 0x37b3, 0x263a,
    0xdecd, 0xcf44, 0xfddf, 0xec56, 0x98e9, 0x8960, 0xbbfb, 0xaa72,
    0x6306, 0x728f, 0x4014, 0x519d, 0x2522, 0x34ab, 0x0630, 0x17b9,
    0xef4e, 0xfec7, 0xcc5c, 0xddd5, 0xa96a, 0xb8e3, 0x8a78, 0x9bf1,
    0x7387, 0x620e, 0x5095, 0x411c, 0x35a3, 0x242a, 0x16b1, 0x0738,
    0xffcf, 0xee46, 0xdcdd, 0xcd54, 0xb9eb, 0xa862, 0x9af9, 0x8b70,
    0x8408, 0x9581, 0xa71a, 0xb693, 0xc22c, 0xd3a5, 0xe13e, 0xf0b7,
    0x0840, 0x19c9, 0x2b52, 0x3adb, 0x4e64, 0x5fed, 0x6d76, 0x7cff,
    0x9489, 0x8500, 0xb79b, 0xa612, 0xd2ad, 0xc324, 0xf1bf, 0xe036,
    0x18c1, 0x0948, 0x3bd3, 0x2a5a, 0x5ee5, 0x4f6c, 0x7df7, 0x6c7e,
    0xa50a, 0xb483, 0x8618, 0x9791, 0xe32e, 0xf2a7, 0xc03c, 0xd1b5,
    0x2942, 0x38cb, 0x0a50, 0x1bd9, 0x6f66, 0x7eef, 0x4c74, 0x5dfd,
    0xb58b, 0xa402, 0x9699, 0x8710, 0xf3af, 0xe226, 0xd0bd, 0xc134,
    0x39c3, 0x284a, 0x1ad1, 0x0b58, 0x7fe7, 0x6e6e, 0x5cf5, 0x4d7c,
    0xc60c, 0xd785, 0xe51e, 0xf497, 0x8028, 0x91a1, 0xa33a, 0xb2b3,
    0x4a44, 0x5bcd, 0x6956, 0x78df, 0x0c60, 0x1de9, 0x2f72, 0x3efb,
    0xd68d, 0xc704, 0xf59f, 0xe416, 0x90a9, 0x8120, 0xb3bb, 0xa232,
    0x5ac5, 0x4b4c, 0x79d7, 0x685e, 0x1ce1, 0x0d68, 0x3ff3, 0x2e7a,
    0xe70e, 0xf687, 0xc41c, 0xd595, 0xa12a, 0xb0a3, 0x8238, 0x93b1,
    0x6b46, 0x7acf, 0x4854, 0x59dd, 0x2d62, 0x3ceb, 0x0e70, 0x1ff9,
    0xf78f, 0xe606, 0xd49d, 0xc514, 0xb1ab, 0xa022, 0x92b9, 0x8330,
    0x7bc7, 0x6a4e, 0x58d5, 0x495c, 0x3de3, 0x2c6a, 0x1ef1, 0x0f78
	);

//
// Computes the checksum accoding to the following parameters
//  Width:         	   16
//  Reflect output:    True
//  Reflect input:     True
//  Xor output:        0xc286
//  Xor Input:         0xffff
//  Polynomial:        0x1021
// Running this crc16 for the string "123456789" should return 0x17ad
//
function crc16($data,$len)
{
	global $crc16_table;
	// Initialize the crc to all 1s
    $crc = 0xffff;
	// Compute the crc
	for ($i = 0; $i < $len; $i++)
	{
        $tbl_idx = ($crc ^ ord($data[$i])) & 0xff;
        $crc = ($crc16_table[$tbl_idx] ^ ($crc >> 8)) & 0xffff;
    }
	# Do the final xor, and reverse the Endianness
	$crc ^= 0xc286;
	return (($crc >> 8) + ($crc << 8)) & 0xffff;
}

//
// crc8 computes an 8-bit crc using the polynomial x8 + x5 + x4 + 1
// From Koopman, et al:
//   http://www.ece.cmu.edu/~koopman/roses/dsn04/koopman04_crc_poly_embedded.pdf
// This polynomial is 100110001 = 0x131, or in Koopman's notation, 10011000 = 0x98.
//
// From http://stackoverflow.com/questions/14079444/how-to-generate-8bit-crc-in-php/14081891#14081891
//
function crc8($string,$len)
{
    global $crc8_table;

    $crc = 0xff;
    for ($ii1=0;$ii1<$len;$ii1++){
        $crc = $crc8_table[($crc ^ ($string[$ii1]))];
    }
    return $crc ^ 0xff;
}


/*
checksum used in an oregon scientic module
uint8_t _crc8( uint8_t *addr, uint8_t len)
{
	uint8_t crc = 0;

	// Indicated changes are from reference CRC-8 function in OneWire library
	while (len--) {
		uint8_t inbyte = *addr++;
		uint8_t i;
		for (i = 8; i; i--) {
			uint8_t mix = (crc ^ inbyte) & 0x80; // changed from & 0x01
			crc <<= 1; // changed from right shift
			if (mix) crc ^= 0x31;// changed from 0x8C;
			inbyte <<= 1; // changed from right shift
		}
	}
	return crc;
}
*/


//
// csumones8 does an 8-bit ones complement checksum on the given string,
//  over the length given.
//
function csumones8($string,$len)
{
	$sum = 0;
	for ($i = 0; $i < $len; $i++)
	{
		$sum += ord($string[$i]);
		if ($sum > 0xFF)
			$sum = ($sum + 1) & 0xFF;
	}
	return $sum;
}

//
// checksumnyb does a checksum of string instr, one nybble at a time. 
//  $len says how many bytes of instr to use
//
function checksumnyb($instr,$len)
{
	$sum = 0;
	for ($i = 0;$i < $len;$i++)
	{
		$sum += (ord($instr[$i]) & 0xF) + (ord($instr[$i]) >> 4);
	}
	return $sum;
}

//
// Simple 8 bit checksum for some packets
//
function checksum8($buffer)
{
    $count=strlen($buffer);
    $byte_array=unpack('C*',$buffer);
    $sum=0;
    for($i=1;$i<=$count;$i++)
    {
        $sum=$sum+$byte_array[$i];
    }
    return($sum&0xff);
}

function checksum8a($buffer)
{
    $count=strlen($buffer);
    $byte_array=unpack('C*',$buffer);
    $sum=0;
    for($i=1;$i<=$count;$i++)
    {
        $sum=$sum+$byte_array[$i];
                echo("    sum=".dechex($sum&0xff)."   ");
    }
    return($sum&0xff);
}

function checksum16($buffer)
{
	$len=strlen($buffer);
    $sum=0;
    for($i=0;$i<$len;$i++)
    {
        $sum+=ord($buffer[$i]);
    }
    return($sum&0xffff);
}

function xor8($buffer)
{
    $count=strlen($buffer);
    $byte_array=unpack('C*',$buffer);
    $crc=0;
    for($i=1;$i<=$count;$i++)
    {
        $crc=$crc^$byte_array[$i];
    }
    return($crc&0xff);
}

//--------------------------------------------------------------------------------------------------------------
// Packet processing begins here:
//--------------------------------------------------------------------------------------------------------------


// Parse the incoming response, first get the headers
$head=getallheaders();
$http_send_ctype = true;

$reply="";
// Get the Identify
if(array_key_exists("HTTP_IDENTIFY",$head)) {
	$http_id=$head["HTTP_IDENTIFY"];
	// Split it into 4 parts
	$output = explode(":", $http_id);
	$mac=$output[0];
	$id1=$output[1];
	$key=$output[2];
	$id2=$output[3];

	// Log it
	//echo("ID= $id1:$id2<br>mac=$mac<br>key=$key<br>\n");

	//
	// Check request and do the appropriate reply
	//
	if(("00"==$id1) && ("10"==$id2))
	{
		$postdata = file_get_contents("php://input");
		write_dump_log($postdata,"00:10");

		// Power Up Packet for unregistered gateway (00:01)
		// Ww should use this to notify user server configuration is OK

		// Just reply with 10:00
		header('HTTP_FLAGS: 10:00');
		$reply="";
	}
	else if(("00"==$id1) && ("20"==$id2))
	{
		// Push button Registration Packet (00:02)  When the button is pushed on the gateway and it is unregistered it will send this packet
		// We can then reply to this packet with registration information.   We send a transofmration key and it uses this and the default
		// gateway key to generate a new key that will be used in the future.  I do not know how the transofmration works, but it doesn't matter
		// you can just store it and recognize it, or ignore it and just use the MAC serial number.
		//
		$postdata = file_get_contents("php://input");

		write_dump_log($postdata,"00:20");
		

		// I try to put my own server in here, but it doesn't seem to matter or update anything
		$weather_server="box.weatherdirect.com";                           // So far this doesn't seem to matter

		// Likely we should check the MAC and only register it if we have set it in motion somehow
		// Unregistered packet, do we need to register this one?
		// Check $mac against registration pending.


		// This is the key that is used to transform the default registration code to the new one, I don't know the transform, so I
		// Just use all zeros.  Likely we can capture a bunch of key+zeros=transform and figure it out.
		//
		// We retply with 20:00 and config:
		// 1st 8 b
		$new_key=chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0);       // This is used to generate new key
																				// no idea on transform, so use zeros, if everyone
																				// uses zeros, maybe we can collect enough data
																				// to figure out transform.
		$new_server=str_pad($weather_server, 0x98 , chr(0));                    // Length 0x98
		$new_server2=str_pad($weather_server.chr(0).$weather_server,0x56, chr(0)); // set the weather server, doesn't seem to matter
		$end=chr(0).chr(0).chr(0).chr(0).chr(0).chr(0xff);

		$reply=$new_key.$new_server.$new_server2.$end;

		header('HTTP_FLAGS: 20:00');                        // Also seen reply 20:01 maybe when it is already registered?
															// To detect this we would likely have to know the transform, or
															// how to tell if it has been transformed, or keep track after transform

	}
	else if(("00"==$id1) && ("30"==$id2))
	{
		$postdata = file_get_contents("php://input");
		write_dump_log($postdata,"00:30");
		// got this afther the 00:70 packet was responded to, I think you must ack this packet for config to stick.
		header('HTTP_FLAGS: 30:00');
	}
	else if(("00"==$id1) && ("70"==$id2))
	{
		$postdata = file_get_contents("php://input");
		write_dump_log($postdata,"00:70");
		//
		// Gateway Ping? Data seems to be controlling frequency of packets
		//
		header('HTTP_FLAGS: 70:00');
		$reply="";
		$reply.=chr(0xff).chr(0xff).chr(0xff).chr(0xff).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0);
		$reply.=chr(0).chr(0).chr(0).chr(0).chr(0).chr(0);
		
		// Pace in seconds of (00:70) packet request, msb first. IE chr(0).chr(10) would be 10 seconds
		// chr(0).chr(0xf0) = 240 seconds (this seems to be default)
		$reply.=chr(0).chr(0xf0);                                                     

	}
	else if(("7F"==$id1) && ("10"==$id2))
	{
		$postdata = file_get_contents("php://input");
		write_dump_log($postdata,"7F:10");

		// Don't reply to this packet unless we have decided it is OK
		$do_reply=0;
		// This is the first packet that WS send to register itself, It specifies its serial number as the first 8 digits of the packet, if
		// the serial number is default, it
		// there should be 13 bytes of ID data on request
		// First parse out the incoming serial number
		$postdata = file_get_contents("php://input");
		//
		// convert the post data to an ascii representation
		$asc_data=bin2hex($postdata);
			   
		// Get the serial number from the request data
		$serial_number=substr($postdata,0,8);

	   // Check if this is the default serial number
		if(0==strcmp($serial_number,pack("H*" , "0102030405060708")))
		{
			// Default Serial Number, refuse it OR check if we need to match it and set a new serialnumber
		   // we should set a new serial number only if the user wants one, really though it should be done
		   // by lacrosse alerts.
		   $do_reply=0;
		   if($register_this_serial)
		   {
				// Do you really want to do this?
				//$do_reply=1;
		   }
		
		}
		else if(0==strcmp($serial_number,$station_serial))
		{
			// For now do not reply unless it is the one we are interested in
			$do_reply=1;
		}
		else
		{
			$do_reply=0;
		}

		if($do_reply)
		{
			// Reply with 14:00 and 38 bytes of data.  This data is likely configuation of the WS
			header('HTTP_FLAGS: 14:00');
			//
			// Important, the reply packet 14:00 can set the serial number of the weather station if the weather station has
			// default serial number, ie 01 02 03 04 05 06 07 08.  It doesn't seem to be resettable after it has been changed
			// the first time, so it might be advisable to register with the Lacrosse server first to get a good serial number
			// written, so if you ever wanted to go back to lacrosse service you could.
			//
			// If the device has a serial number, then it must match below or this packet is ignored.  The same holds true
			// for below, if you don't populate the correct serial number then the internet display on the weather station 
			// will not light.
			//
			//
			$reply="";
			$reply.=chr(1);                                                             // Seems to be always 1
			// if we have a valid serial number in the request, stuff it here:
			$reply.=$station_serial;
			//$reply.=chr(0x7f).chr(0xff);												// All serial nubers begin with this
			//$reply.=chr(0x0).chr(0x0).chr(0x00).chr(0x00).chr(0x00).chr(0x00);		// Serial number, changable part 6 bytes, this is set above
			$reply.=chr(0).chr(0x30).chr(0).chr(0xf).chr(0x00).chr(0x0).chr(0x00);    // ?
			$reply.=chr(0xf).chr(0).chr(0x0).chr(0).chr(0x77).chr(0x0);				// ?

			$reply.=chr(0xe).chr(0xff);                                          // Skydiver calls this Ephoch, I do nothing here

			$dateint = time(); // Grab the Unix date/time atomically, so there is no possibility of date/time field overflow causing errors
			// The time here should be sent in UTC, which this doesn't do. Somewhere the time zone and DST flags probably need 
			// to be embedded
			$reply.=chr(bin2bcd(date('h',$dateint))).chr(bin2bcd(date('i',$dateint))).chr(bin2bcd(date('s',$dateint)));	// Server hour, min and second in BCD format
			$reply.=chr(bin2bcd(date('d',$dateint))).chr(bin2bcd(date('m',$dateint))).chr(bin2bcd(date('y',$dateint)));   // Day Month and Year in BCD format
			$reply.=chr(0x53);																// unknown Server DateTime
	  
			$reply.=chr(0x7);										// Unknown
			$reply.=chr(0x5);                                       // LCD brightness this value+1 = value on display settings
			$reply.=chr(0x0).chr(0x0);							    // (word) beep weather station on this packet reply on internet update >0, nobeep=0 (?what else)
			$reply.=chr(0x0);										// Unknown
			$reply.=chr(0x07);										// Unknown (is 0x7 on lacrosse alerts)
			// Checksum
			$reply=$reply.chr(checksum8($reply));
		}

	}
	else if(("00"==$id1) && ("14"==$id2))
	{
		// Got this right after the 7F:10, send 14 bytes of data, I think this reply is needed to "seal the deal" on registration
		$postdata = file_get_contents("php://input");
		write_dump_log($postdata,"00:14");

		header('HTTP_FLAGS: 1C:00');
		$reply="";
	}
	else if(("01"==$id1) && ("14"==$id2))
	{
		// This sends 14 bytes of data with no reply, the data is the new serial number in the same format as 7F:10 except for one
		// extra byte on end
		$postdata = file_get_contents("php://input");
		write_dump_log($postdata,"01:14");
		
		header('HTTP_FLAGS: 1C:00');
		$reply="";
	}
	else if(("01"==$id1) && ("00"==$id2))
	{
		// Weather Station Ping Packet, reply to this packet keeps the Weather Station Happy and time Synced.
		//
		// This sends 5 bytes of data, and is responded to by 38 bytes of data, we do not do anything with the 5 bytes of data
		// The weather station sends
		//
		$postdata = file_get_contents("php://input");
		write_dump_log($postdata,"01:00");

		/*  From Skydiver:
		TSend0100Packet = PACKED RECORD
			Payload:  TTrippleByte; // unknown
			Checksum: Word;
		END;
		*/                    
		header('HTTP_FLAGS: 14:01');
		$reply="";
		//
		// Reply should be same as above 38 byte packet
		//
	  $reply="";
	  $reply.=chr(1);
	  // You should hard code your serial number here, or if the server handles multiple weather stations, use a 
	  // MAC ($mac) to serial number translater lookup so you can reply with the correct serial number for your weather station
	  // For now just use the hardcode value above. That value is 16 bytes long, from byte 1 through 8
	  $reply.=$station_serial;
	  // Bytes    0x09      0x0a      0x0b      0x0c      0x0d      0x0e      0x0f
	  $reply.=chr(0x00).chr(0x32).chr(0x00).chr(0x0b).chr(0x00).chr(0x00).chr(0x00);    // ?
	  // Bytes    0x10      0x11      0x12      0x13      0x14      0x15
	  $reply.=chr(0x0f).chr(0x00).chr(0x00).chr(0x00).chr(0x03).chr(0x00);				// ?
	  // Bytes    0x16      0x17
	  $reply.=chr(0x3e).chr(0xde);                                          // Skydiver calls this Epoch, I do nothing here

   	  $dateint = time(); // Grab the Unix date/time, so there is no possibility of overflow causing an erroneous part of the date/time
	  $reply.=chr(bin2bcd(date('h',$dateint))).chr(bin2bcd(date('i',$dateint))).chr(bin2bcd(date('s',$dateint)));	// Server hour, min and second in BCD format
	  $reply.=chr(bin2bcd(date('d',$dateint))).chr(bin2bcd(date('m',$dateint))).chr(bin2bcd(date('y',$dateint)));   // Day Month and Year in BCD format
	  // The date is 6 bytes, so the next one is 0x1e
	  $reply.=chr(0x53);																// unknown Server DateTime
	  
	  $reply.=chr(0x07);										// Byte 0x1f  Unknown 
	  $reply.=chr(0x04);                                        // Byte 0x20 LCD brightness this value+1 = value on display settings
	  $reply.=chr(0x00).chr(0x00);							    // Byte 0x21 & 0x22 (word) beep weather station on this packet reply on internet update >0, nobeep=0 (?what else)
	  $reply.=chr(0x00);										// Byte 0x23 Unknown

	  //$reply.=chr(0x07);										// Unknown (is 0x7 on lacrosse alerts)
	  //$reply=$reply.chr(checksum8($reply));
	  // End the packet with a big-endian 2-byte sum of the previous bytes, with an offset of 7
	  $csum=checksum16($reply)+7;
	  $reply.=chr($csum>>8).chr($csum&0xff);
	  write_dump_log($reply,"14:01");
	}
	else if(("01"==$id1) && ("01"==$id2))
	{
		// This packet should contain 197 bytes of data (ive also seen 102 bytes of data, but I think it should always be 197 bytes of data).  
		// This is the sensor data we should decode, use skydivers data to decode it and log to a database/mrtg/etc...
		$postdata = file_get_contents("php://input");
		//
		// At this point we only know how to decode this packet if it is 197 bytes, although I've seen other sizes, but it might be corrupt wireless
		// so don't do anything unless 197 bytes.  It does seem like 210 byte packets are common, but I have not captured these from
		// Lacrosse alerts site.   
		//
		// 197 byte packets start with : 01 64 14 12 01 40 30 40 63
		// 210 byte packets start with : 21 64 14 0b 07 1c 02 66 00
		//  30 byte packets start with : 21 64 10 01 07 1c 07
		///
		if(197==strlen($postdata))
		{
			// Convert the post data to an array of nybbles
			$nybs = post2nyb($postdata);
			$outstr=date('Y-m-d H:i:s')."   Packet Type 01:01";
			//$slen=strlen($postdata);
			//$outstr.="{$slen} bytes "; // String length
			// Temperatures are formatted to accommodate 100+ degrees, so 
			//  we don't put a space after the colon
			$outstr.="\n";
			$outstr.=" Outdoor: ";
			$outstr.="Temp:".temp2str($nybs,        0x04b)." ";   // byte 25 lower nybble
			$outstr.="Max:".temp2str($nybs,         0x041)." ";   // byte 20, lower nybble
			$outstr.=dat2str($nybs,                 0x02d)." ";   // Max Outside Temp Time byte 0x16 lower nybble
			$outstr.="Min:".temp2str($nybs,         0x046)." ";   // byte 23 high nybble
			$outstr.=dat2str($nybs,                 0x037)." ";   // Min Outside Temp Time byte 1b lower nybble
			$outstr.="Humidity: ".hum2str($nybs,    0x0a6)." ";   // byte 53 higher nybble
			$outstr.="Max:".hum2str($nybs,          0x0a2)." ";   // byte 51 higher nybble
			$outstr.=dat2str($nybs,                 0x08e)." ";   // Max Outside Humidity Time byte 47 higher nybble
			$outstr.="Min:".hum2str($nybs,          0x0a4)." ";   // byte 52 higher nybble
			$outstr.=dat2str($nybs,                 0x098)." ";   // Min Outside Humidity Time byte 4c higher nybble
			$outstr.="\n ";
			$outstr.=" Indoor: ";
			$outstr.="Temp:".temp2str($nybs,        0x027)." ";   // byte 13 lower nybble
			$outstr.="Max:".temp2str($nybs,         0x01d)." ";   // byte 0e lower nybble
			$outstr.=dat2str($nybs,                 0x009)." ";   // Max Inside Temp Time byte 04 lower nybble
			$outstr.="Min:".temp2str($nybs,         0x022)." ";   // byte 72 higher nybble
			$outstr.=dat2str($nybs,                 0x013)." ";   // Min Inside Temp Time byte 09 lower nybble
			$outstr.="Humidity: ".hum2str($nybs,    0x08c)." ";   // byte 46 higher nybble
			$outstr.="Max:".hum2str($nybs,          0x088)." ";   // byte 44 higher nybble
			$outstr.=dat2str($nybs,                 0x074)." ";   // Max Inside Humidity Time byte 3a higher nybble
			$outstr.="Min:".hum2str($nybs,          0x08a)." ";   // byte 45 higher nybble
			$outstr.=dat2str($nybs,                 0x07e)." ";   // Min Inside Humidity Time byte 3f higher nybble
			//$outstr.="OutTemp2:".temp2str($nybs,  0x06f)." ";   // byte 37 lower nybble
			$outstr.="\n ";
			$outstr.="Windsp:".ws2str($nybs,        0x122)." ";   // Current ave wind speed
			$outstr.="Gust:".ws2str($nybs,          0x140)." ";   // Max Wind Gust in last cycle
			$outstr.="Max Gust:".ws2str($nybs,      0x13a)." ";   // Max Wind Gust since reset
			$outstr.="Max Gust Time:".dat2str($nybs,0x130)." ";   // Time/Date of Max Wind Gust
			$outstr.="WindDir: ".wd2str($nybs,      0x12a)." ";   // Wind Direction History Array
			$outstr.="Barometer: ".baro2str($nybs,  0x14f)." ";   // Current barometer
			$outstr.="Min: ".baro2str($nybs,        0x159)." ";   // Min Barometer
			$outstr.="Max: ".baro2str($nybs,        0x163)." ";   // Max Barometer
			$outstr.="\n ";
			$outstr.="Rain: ";
			$outstr.="1hr: ".rf2str($nybs,          0x0fa,6)." "; // Last 24h rain
			$outstr.="24hr: ".rf2str($nybs,         0x0e4,6)." "; // Last 24h rain
			$outstr.="Week: ".rf2str($nybs,         0x0ce,6)." "; // Previous Week's rain
			$outstr.="Month: ".rf2str($nybs,        0x0b8,7)." "; // Previous month's rain
			$outstr.="Since Reset: ".rf2str($nybs,  0x10b,7)." "; // Rain since last reset
			$outstr.="Reset: ".dat2str($nybs,       0x101)." ";   // Date/Time of Last Rain Reset
			$outstr.="Last Heavy Rain Ended: ".dat2str($nybs,0x0ea)." ";   // Date/Time of Last 1-hour rain window
			$outstr.="\n ";
			$outstr.="Unknown Dates: ";
			$outstr.="0x051:".dat2str($nybs,        0x051)." ";   // Unknown Time byte 28
			$outstr.="0x05b:".dat2str($nybs,        0x05b)." ";   // Unknown Time byte 2d
			$outstr.="0x0d4:".dat2str($nybs,        0x0d4)." ";   // Unknown Time byte 6a
			$outstr.="0x112:".dat2str($nybs,        0x112)." ";   // Unknown Time Nybble 0x112
			$outstr.="0x16c:".dat2str($nybs,        0x16c)." ";   // Unknown Time byte b6
			$outstr.="0x176:".dat2str($nybs,        0x176)." ";   // Unknown Time byte bb
			$outstr.="\n ";
			$outstr .= "Checksums for all but last two bytes: ";
			$postlen = strlen($postdata);
			//$outstr.= sprintf("checksumnyb:%03x ",checksumnyb($postdata,$postlen-2));
			//$outstr.= sprintf("checksum8:%02x ",checksum8(substr($postdata,0,$postlen-2)));
			//$outstr.= sprintf("xor8:%02x ",xor8(substr($postdata,0,$postlen-2)));
			//$outstr.= sprintf("crc8:%02x ",crc8($postdata,$postlen-2));
			//$outstr.= sprintf("csumones8:%02x ",csumones8($postdata,$postlen-2));
			$outstr.= sprintf("CRC16: %04x",crc16($postdata,$postlen-2));
			$outstr.="\n";
			//$outstr .= "Checksums for all but the 2 last bytes:  ";
			//$outstr.= sprintf("checksumnyb:%03x ",checksumnyb($postdata,$postlen-1));
			//$outstr.= sprintf("checksum8:%02x ",checksum8(substr($postdata,0,$postlen-1)));
			//$outstr.= sprintf("xor8:%02x ",xor8(substr($postdata,0,$postlen-1)));
			//$outstr.= sprintf("crc8:%02x ",crc8($postdata,$postlen-1));
			//$outstr.= sprintf("csumones8:%02x ",csumones8($postdata,$postlen-1));
			//$outstr.= sprintf("CRC16: %04x",crc16("123456789",9));
			//$outstr.="\n ";
			// Log BCD data to a file
			$outstr.= "\n".dump_unknown($postdata);
			write_data_log($outstr);

			
			// Log a hex dump of 
			write_dump_log($postdata,"01:01");
			// Write the post data to a file, for possible later analysis.
			//  The post data doesn't include a time stamp, so we prepend a 
			//  4-byte unix time stamp to each record when it's written. Each 
			//  record will be 201 bytes..
			$packtime = pack("l",time());
			// For now, down't include the time...
			file_put_contents("postdata197.bin",$packtime.$postdata,FILE_APPEND);
			// We should checksum the packet here before decode.  I believe that the checksum is generated by the 
			// weather station before it is sent over the wireless link. So the packet can be corrupted,  so far I have not
			// figured out the checksum though, so we just decode it if 197 bytes
		    // if(checksum8a($postdata))
			{
			// Decode post data:
			}
			// Reply with 00:00 no data    
			//header('HTTP_FLAGS: 00:00');
		} else {
			// Log other lengths of postdata too
			//write_dump_log($postdata,"01:01");
			// And write a binary file of the data, named according to postdata length
			$filename = "postdata".sprintf("%d",strlen($postdata)).".bin";
			$packtime = pack("l",time());
			file_put_contents($filename,$packtime.$postdata,FILE_APPEND);
			# For the 210-byte (data history?) packets, don't reply with the content-type header
			if (strlen($postdata) == 210)
			{
				$http_send_ctype = false;
			}
		}
	 
	   // Reply with 00:00 no data    
	   header('HTTP_FLAGS: 00:00');
	   $reply="";
	}



	//-----------------------------
	//All output created here
	//-----------------------------
	header('Server: Microsoft-II/6.0');
	header('X-Powered-By: ASP.NET');
	header('X-ApsNet-Version: 2.0.50727');
	header('Cache-Control: private');
	# For testing, comment out the following line, and comment in the 
	#  phpinfo line below it. This won't work for the Lacrosse gateway,
	#  but it will work in a browser to see that it's responding
	if ($http_send_ctype)
	{
		header('Content-Type: application/octet-stream');
	}
	#phpinfo();
}

echo $reply;

?>
