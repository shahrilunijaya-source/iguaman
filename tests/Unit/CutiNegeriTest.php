<?php

namespace Tests\Unit;

use App\Support\CutiNegeri;
use Tests\TestCase;

/**
 * EPIC G — ref_cuti.idnegeri 16-slot encoding. Pure (no DB).
 */
class CutiNegeriTest extends TestCase
{
    public function test_encode_builds_16_slot_comma_string(): void
    {
        $s = CutiNegeri::encode([1, 4, 16]);

        $this->assertSame('01,00,00,04,00,00,00,00,00,00,00,00,00,00,00,16', $s);
        $this->assertSame(47, strlen($s));               // 16 codes + 15 commas
        $this->assertCount(16, explode(',', $s));
    }

    public function test_encode_all_states(): void
    {
        $s = CutiNegeri::encode(range(1, 16));

        $this->assertSame('01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16', $s);
        $this->assertTrue(CutiNegeri::isAll($s));
    }

    public function test_encode_empty_is_all_zero(): void
    {
        $this->assertSame(str_repeat('00,', 15).'00', CutiNegeri::encode([]));
        $this->assertFalse(CutiNegeri::isAll(CutiNegeri::encode([])));
    }

    public function test_decode_roundtrips_position_based(): void
    {
        $this->assertSame([1, 4, 16], CutiNegeri::decode('01,00,00,04,00,00,00,00,00,00,00,00,00,00,00,16'));
        $this->assertSame([], CutiNegeri::decode(null));
        $this->assertSame([], CutiNegeri::decode(''));
        $this->assertSame(range(1, 16), CutiNegeri::decode(CutiNegeri::encode(range(1, 16))));
    }

    public function test_decode_is_position_based_not_value_based(): void
    {
        // Even if a slot carries a stray non-"00" value, the SELECTED state is
        // its position (legacy substr reader), so slot 2 set => state 2.
        $this->assertSame([2], CutiNegeri::decode('00,99,00,00,00,00,00,00,00,00,00,00,00,00,00,00'));
    }

    public function test_labels_uses_provided_names_then_falls_back(): void
    {
        $names = [1 => 'Johor', 4 => 'Melaka', 16 => 'W.P. Putrajaya'];
        $this->assertSame(['Johor', 'Melaka', 'W.P. Putrajaya'], CutiNegeri::labels(CutiNegeri::encode([1, 4, 16]), $names));

        // Fallback to the built-in STATES map when no names passed.
        $this->assertSame(['Kedah'], CutiNegeri::labels(CutiNegeri::encode([2])));
    }
}
