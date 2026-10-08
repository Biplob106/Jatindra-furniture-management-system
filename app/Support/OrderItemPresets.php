<?php

namespace App\Support;

/**
 * The choices offered as chips on the order form.
 *
 * Plain strings, not enums: wood_type and polish_type are free text in the
 * schema and the form also accepts an "অন্যান্য" value typed by hand. The
 * stored value is the Bengali word itself, so old orders read the same.
 */
final class OrderItemPresets
{
    /** @var list<string> */
    public const WOOD_TYPES = ['সেগুন', 'মেহগনি', 'চাম্বল', 'গর্জন', 'কড়ই', 'শাল', 'বোর্ড'];

    /** @var list<string> */
    public const POLISH_TYPES = ['বার্নিশ', 'লেকার', 'পিইউ', 'ডুকো', 'রং', 'পলিশ ছাড়া'];
}
