<?php
/**
*
* @ This file is created by http://DeZender.Net
* @ deZender (PHP7 Decoder for ionCube Encoder)
*
* @ Version			:	5.0.1.0
* @ Author			:	DeZender
* @ Release on		:	22.04.2022
* @ Official site	:	http://DeZender.Net
*
*/

class TS
{
	static public $rBuffer = null;
	static public $rPosition = null;
	static public $rByte = null;
	static public $rIndex = null;

	public function setPacket($rBuffer)
	{
		self::$rBuffer = $rBuffer;
		self::$rPosition = 7;
		self::$rByte = array_values(unpack('C', self::$rBuffer[0]))[0];
		self::$rIndex = 1;
	}

	public function getBits($rNumBits)
	{
		$rNum = 0;
		$rMask = 1 << self::$rPosition;

		while (0 < $rNumBits) {
			$rNumBits -= 1;
			$rNum <<= 1;

			if ($rMask & self::$rByte) {
				$rNum |= 1;
			}

			$rMask >>= 1;
			self::$rPosition -= 1;

			if (self::$rPosition < 0) {
				self::$rPosition = 7;
				$rMask = 1 << self::$rPosition;

				if (self::$rIndex < strlen(self::$rBuffer)) {
					self::$rByte = array_values(unpack('C', self::$rBuffer[self::$rIndex]))[0];
				}
				else {
					self::$rByte = 0;
				}

				self::$rIndex += 1;
			}
		}

		return $rNum;
	}

	public function parsePacket()
	{
		$rReturn = ['sync_byte' => self::getBits(8), 'transport_error_indicator' => self::getBits(1), 'payload_unit_start_indicator' => self::getBits(1), 'transport_priority' => self::getBits(1), 'pid' => self::getBits(13), 'scrambling_control' => self::getBits(2), 'adaptation_field_exist' => self::getBits(2), 'continuity_counter' => self::getBits(4)];
		if ($rReturn['adaptation_field_exist'] || ($rReturn['adaptation_field_exist'] == 3)) {
			$rTell = self::$rIndex;
			$rReturn['adaptation_field_length'] = self::getBits(8);

			if ($rReturn['adaptation_field_length'] == 7) {
				$rReturn = array_merge($rReturn, ['discontinuity_indicator' => self::getBits(1), 'random_access_indicator' => self::getBits(1), 'priority_indicator' => self::getBits(1), 'pcr_flag' => self::getBits(1), 'opcr_flag' => self::getBits(1), 'splicing_point_flag' => self::getBits(1), 'transport_private_data_flag' => self::getBits(1), 'adaptation_field_extension_flag' => self::getBits(1)]);

				if ($rReturn['pcr_flag']) {
					$rReturn = array_merge($rReturn, ['program_clock_reference_base' => self::getBits(33), 'reserved_pcr' => self::getBits(6), 'program_clock_reference_extension' => self::getBits(9)]);
					$rReturn['pcr'] = (($rReturn['program_clock_reference_base'] * 300) + $rReturn['program_clock_reference_extension']) / 27000000.0;
				}

				if ($rReturn['opcr_flag']) {
					$rReturn = array_merge($rReturn, ['original_program_clock_reference_base' => self::getBits(33), 'reserved_opcr' => self::getBits(6), 'original_program_clock_reference_extension' => self::getBits(9)]);
					$rReturn['opcr'] = (($rReturn['original_program_clock_reference_base'] * 300) + $rReturn['original_program_clock_reference_extension']) / 27000000.0;
				}

				if ($rReturn['splicing_point_flag']) {
					$rReturn['splice_countdown'] = self::getBits(8);
				}

				if ($rReturn['transport_private_data_flag']) {
					$rReturn['transport_private_data_length'] = self::getBits(8);
					self::stepBytes($rReturn['transport_private_data_length']);
				}
			}
			else {
				unset($rReturn['adaptation_field_length']);
			}
		}

		if ($rReturn['pid'] == 0) {
			$rReturn['pointer_field'] = self::getBits(8);

			if ($rReturn['pointer_field']) {
				self::stepBytes($rReturn['pointer_field']);
			}

			$rReturn = array_merge($rReturn, ['type' => 'pat', 'table_id' => self::getBits(8), 'section_syntax_indicator' => self::getBits(1), 'marker' => self::getBits(1), 'reserved_1' => self::getBits(2), 'section_length' => self::getBits(12), 'transport_stream_id' => self::getBits(16), 'reserved_2' => self::getBits(2), 'version_number' => self::getBits(5), 'current_next_indicator' => self::getBits(1), 'section_number' => self::getBits(8), 'last_section_number' => self::getBits(8)]);
		}
		else if ($rReturn['payload_unit_start_indicator']) {
			self::$rBuffer = substr(self::$rBuffer, self::$rIndex, 188);
			self::$rIndex = 0;
			$rReturn = array_merge($rReturn, ['type' => 'pes', 'packet_start_prefix' => self::getBits(24), 'stream_id' => self::getBits(8), 'pes_packet_length' => self::getBits(16), 'marker_bits' => self::getBits(2), 'scrambling_control' => self::getBits(2), 'priority' => self::getBits(1), 'data_alignment_indicator' => self::getBits(1), 'copyright' => self::getBits(1), 'original_or_copy' => self::getBits(1), 'pts_dts_indicator' => self::getBits(2), 'escr_flag' => self::getBits(1), 'es_rate_flag' => self::getBits(1), 'dsm_trick_mode_flag' => self::getBits(1), 'additional_copy_info_flag' => self::getBits(1), 'crc_flag' => self::getBits(1), 'extension_flag' => self::getBits(1), 'pes_header_length' => self::getBits(8)]);
			if (($rReturn['pts_dts_indicator'] == 2) || ($rReturn['pts_dts_indicator'] == 3)) {
				self::getBits(4);
				$rPTSA = self::getBits(3);
				self::getBits(1);
				$rPTSB = self::getBits(15);
				self::getBits(1);
				$rPTSC = self::getBits(15);
				self::getBits(1);
				$rReturn['pts'] = ($rPTSA << 30) + ($rPTSB << 15) + $rPTSC;
			}

			if ($rReturn['pts_dts_indicator'] == 3) {
				self::getBits(4);
				$rDTSA = self::getBits(3);
				self::getBits(1);
				$rDTSB = self::getBits(15);
				self::getBits(1);
				$rDTSC = self::getBits(15);
				self::getBits(1);
				$rReturn['dts'] = ($rDTSA << 30) + ($rDTSB << 15) + $rDTSC;
			}
		}

		return $rReturn;
	}

	public function stepBytes($rBytes)
	{
		$rData = substr(self::$rBuffer, self::$rIndex - 1, $rBytes);

		foreach (range(0, $rBytes) as $i) {
			self::getBits(8);
		}

		return $rData;
	}
}

?>