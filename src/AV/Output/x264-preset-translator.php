<?php

/*
Copyright (c) 2012-2020 Maarten Baert <maarten-baert@hotmail.com>

This file is part of SimpleScreenRecorder.

SimpleScreenRecorder is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

SimpleScreenRecorder is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with SimpleScreenRecorder.  If not, see <http://www.gnu.org/licenses/>.
*/

// This script converts old ffmpeg preset files for x264 into C++ code that sets the corresponding AVCodecContext member variables.

$presets = array(
	"ultrafast",
	"superfast",
	"veryfast",
	"faster",
	"fast",
	"medium",
	"slow",
	"slower",
	"veryslow",
	"placebo",
);

$translator = array(
	"coder" => "coder_type",
	"subq" => "me_subpel_quality",
	"me_range" => "me_range",
	"g" => "gop_size",
	"keyint_min" => "keyint_min",
	"sc_threshold" => "scenechange_threshold",
	"i_qfactor" => "i_quant_factor",
	"b_strategy" => "b_frame_strategy",
	"qcomp" => "qcompress",
	"qmin" => "qmin",
	"qmax" => "qmax",
	"qdiff" => "max_qdiff",
	"bf" => "max_b_frames",
	"refs" => "refs",
	"directpred" => "directpred",
	"trellis" => "trellis",
	"wpredp" => "weighted_p_pred",
	"aq_mode" => "aq_mode",
	"rc_lookahead" => "rc_lookahead",
	"me_method" => "me_method", // enum
	"flags" => "flags", // bitset
	"flags2" => "flags2", // bitset
	"cmp" => "me_cmp", // bitset
	"partitions" => "partitions", // bitset
);
$translator_enum = array(
	"me_method" => array(
		"dia" => "ME_EPZS",
		"hex" => "ME_HEX",
		"umh" => "ME_UMH",
		"esa" => "ME_FULL",
		"tesa" => "ME_TESA",
	),
);
$translator_bitset = array(
	"flags" => array(
		"loop" => "CODEC_FLAG_LOOP_FILTER",
		"cgop" => "CODEC_FLAG_CLOSED_GOP",
	),
	"flags2" => array(
		"bpyramid" => "CODEC_FLAG2_BPYRAMID",
		"mixed_refs" => "CODEC_FLAG2_MIXED_REFS",
		"wpred" => "CODEC_FLAG2_WPRED",
		"dct8x8" => "CODEC_FLAG2_8X8DCT",
		"fastpskip" => "CODEC_FLAG2_FASTPSKIP",
		"mbtree" => "CODEC_FLAG2_MBTREE",
	),
	"cmp" => array(
		"chroma" => "1",
	),
	"partitions" => array(
		"parti4x4" => "X264_PART_I4X4",
		"parti8x8" => "X264_PART_I8X8",
		"partp8x8" => "X264_PART_P8X8",
		"partp4x4" => "X264_PART_P4X4",
		"partb8x8" => "X264_PART_B8X8",
	),
);

$out_header = "";
$out_header .= "#pragma once\n";
$out_header .= "#include \"Global.h\"\n\n";
$out_header .= "// This file was generated by 'x264-preset-translator.php', don't edit it.\n\n";
$out_header .= "#if !SSR_USE_AVCODEC_PRIVATE_PRESET\n\n";

$out_source = "";
$out_source .= "#include \"X264Presets.h\"\n\n";
$out_source .= "// This file was generated by 'x264-preset-translator.php', don't edit it.\n\n";
$out_source .= "#if !SSR_USE_AVCODEC_PRIVATE_PRESET\n\n";

$out_header .= "void X264Preset(AVCodecContext* cc, const char* preset);\n\n";
$out_source .= "void X264Preset(AVCodecContext* cc, const char* preset) {\n";

foreach($presets as $preset) {
	$out_source .= "\tif(strcmp(preset, \"" . $preset . "\") == 0)\n";
	$out_source .= "\t\tX264Preset_" . $preset . "(cc);\n";
}

$out_source .= "}\n\n";

foreach($presets as $preset) {
	
	$data = file_get_contents("/usr/local/share/ffmpeg/libx264-" . $preset . ".ffpreset");
	if($data === FALSE)
		die("Can't find preset '" . $preset . "'!\n");
	$lines = explode("\n", str_replace("\r", "", $data));
	
	$out_header .= "void X264Preset_" . $preset . "(AVCodecContext* cc);\n";
	$out_source .= "void X264Preset_" . $preset . "(AVCodecContext* cc) {\n";
	foreach($lines as $n => $line) {
		if($line == "")
			continue;
		$parts = explode("=", $line);
		if(count($parts) != 2)
			die("Syntax error at line " . $n . " of file " . $preset . ": " . $line . "\n");
		if(isset($translator[$parts[0]])) {
			if(isset($translator_enum[$parts[0]])) {
				$t = $translator_enum[$parts[0]];
				if(isset($t[$parts[1]])) {
					$out_source .= "\tcc->" . $translator[$parts[0]] . " = " . $t[$parts[1]] . ";\n";
				} else {
					die("Unknown enum value at line " . $n . " of file " . $preset . ": " . $line . "\n");
				}
			} else if(isset($translator_bitset[$parts[0]])) {
				$t = $translator_bitset[$parts[0]];
				$flags = preg_split("/(\+|\-)/", $parts[1], -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);
				foreach($flags as $flag) {
					if(isset($t[$flag[0]])) {
						if($parts[1][$flag[1] - 1] == "+") {
							$out_source .= "\tcc->" . $translator[$parts[0]] . " |= " . $t[$flag[0]] . ";\n";
						} else {
							$out_source .= "\tcc->" . $translator[$parts[0]] . " &= ~" . $t[$flag[0]] . ";\n";
						}
					} else {
						die("Can't translate line " . $n . " of file " . $preset . ": " . $line . "\n"
							. "Unknown flag '" . $flag[0] . "'.\n");
					}
				}
			} else {
				$out_source .= "\tcc->" . $translator[$parts[0]] . " = " . $parts[1] . ";\n";
			}
		} else {
			die("Can't translate line " . $n . " of file " . $preset . ": " . $line . "\n");
		}
	}
	$out_source .= "}\n\n";
	
}

$out_header .= "\n";
$out_header .= "#endif\n";
$out_source .= "#endif\n";

file_put_contents("X264Presets.h", $out_header);
file_put_contents("X264Presets.cpp", $out_source);

?>
