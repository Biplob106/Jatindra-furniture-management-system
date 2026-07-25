<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Bengali validation messages. Every user-facing string in this project is
    | Bengali; validation messages live here and are never written inline in a
    | FormRequest.
    |
    */

    'accepted' => ':attribute গ্রহণ করতে হবে।',
    'accepted_if' => ':other যখন :value হয় তখন :attribute গ্রহণ করতে হবে।',
    'active_url' => ':attribute একটি সঠিক URL হতে হবে।',
    'after' => ':attribute :date তারিখের পরের তারিখ হতে হবে।',
    'after_or_equal' => ':attribute :date তারিখের সমান বা পরের তারিখ হতে হবে।',
    'alpha' => ':attribute শুধু অক্ষর হতে পারবে।',
    'alpha_dash' => ':attribute শুধু অক্ষর, সংখ্যা, ড্যাশ এবং আন্ডারস্কোর হতে পারবে।',
    'alpha_num' => ':attribute শুধু অক্ষর এবং সংখ্যা হতে পারবে।',
    'any_of' => ':attribute সঠিক নয়।',
    'array' => ':attribute একটি অ্যারে হতে হবে।',
    'ascii' => ':attribute শুধু এক বাইটের অক্ষর, সংখ্যা এবং চিহ্ন হতে পারবে।',
    'before' => ':attribute :date তারিখের আগের তারিখ হতে হবে।',
    'before_or_equal' => ':attribute :date তারিখের সমান বা আগের তারিখ হতে হবে।',
    'between' => [
        'array' => ':attribute এ :min থেকে :max টি আইটেম থাকতে হবে।',
        'file' => ':attribute :min থেকে :max কিলোবাইটের মধ্যে হতে হবে।',
        'numeric' => ':attribute :min থেকে :max এর মধ্যে হতে হবে।',
        'string' => ':attribute :min থেকে :max অক্ষরের মধ্যে হতে হবে।',
    ],
    'boolean' => ':attribute হ্যাঁ অথবা না হতে হবে।',
    'can' => ':attribute এ অননুমোদিত মান রয়েছে।',
    'confirmed' => ':attribute নিশ্চিতকরণ মিলছে না।',
    'contains' => ':attribute এ একটি আবশ্যক মান নেই।',
    'current_password' => 'পাসওয়ার্ড সঠিক নয়।',
    'date' => ':attribute একটি সঠিক তারিখ হতে হবে।',
    'date_equals' => ':attribute :date তারিখের সমান হতে হবে।',
    'date_format' => ':attribute :format ফরম্যাট অনুযায়ী হতে হবে।',
    'decimal' => ':attribute এ :decimal ঘর দশমিক থাকতে হবে।',
    'declined' => ':attribute প্রত্যাখ্যান করতে হবে।',
    'declined_if' => ':other যখন :value হয় তখন :attribute প্রত্যাখ্যান করতে হবে।',
    'different' => ':attribute এবং :other আলাদা হতে হবে।',
    'digits' => ':attribute :digits সংখ্যার হতে হবে।',
    'digits_between' => ':attribute :min থেকে :max সংখ্যার মধ্যে হতে হবে।',
    'dimensions' => ':attribute এর ছবির মাপ সঠিক নয়।',
    'distinct' => ':attribute এর মান একাধিকবার দেওয়া হয়েছে।',
    'doesnt_contain' => ':attribute এ নিচের কোনোটি থাকতে পারবে না: :values।',
    'doesnt_end_with' => ':attribute নিচের কোনোটি দিয়ে শেষ হতে পারবে না: :values।',
    'doesnt_start_with' => ':attribute নিচের কোনোটি দিয়ে শুরু হতে পারবে না: :values।',
    'email' => ':attribute একটি সঠিক ইমেইল ঠিকানা হতে হবে।',
    'encoding' => ':attribute :encoding এনকোডিংয়ে হতে হবে।',
    'ends_with' => ':attribute নিচের কোনো একটি দিয়ে শেষ হতে হবে: :values।',
    'enum' => 'নির্বাচিত :attribute সঠিক নয়।',
    'exists' => 'নির্বাচিত :attribute সঠিক নয়।',
    'extensions' => ':attribute এর এক্সটেনশন নিচের কোনো একটি হতে হবে: :values।',
    'file' => ':attribute একটি ফাইল হতে হবে।',
    'filled' => ':attribute এর মান দিতে হবে।',
    'gt' => [
        'array' => ':attribute এ :value টির বেশি আইটেম থাকতে হবে।',
        'file' => ':attribute :value কিলোবাইটের বেশি হতে হবে।',
        'numeric' => ':attribute :value এর চেয়ে বড় হতে হবে।',
        'string' => ':attribute :value অক্ষরের বেশি হতে হবে।',
    ],
    'gte' => [
        'array' => ':attribute এ :value টি বা তার বেশি আইটেম থাকতে হবে।',
        'file' => ':attribute :value কিলোবাইটের সমান বা বেশি হতে হবে।',
        'numeric' => ':attribute :value এর সমান বা বড় হতে হবে।',
        'string' => ':attribute :value অক্ষরের সমান বা বেশি হতে হবে।',
    ],
    'hex_color' => ':attribute একটি সঠিক হেক্সাডেসিমাল রঙ হতে হবে।',
    'image' => ':attribute একটি ছবি হতে হবে।',
    'in' => 'নির্বাচিত :attribute সঠিক নয়।',
    'in_array' => ':attribute :other এর মধ্যে থাকতে হবে।',
    'in_array_keys' => ':attribute এ নিচের অন্তত একটি কী থাকতে হবে: :values।',
    'integer' => ':attribute একটি পূর্ণসংখ্যা হতে হবে।',
    'ip' => ':attribute একটি সঠিক IP ঠিকানা হতে হবে।',
    'ipv4' => ':attribute একটি সঠিক IPv4 ঠিকানা হতে হবে।',
    'ipv6' => ':attribute একটি সঠিক IPv6 ঠিকানা হতে হবে।',
    'json' => ':attribute একটি সঠিক JSON স্ট্রিং হতে হবে।',
    'list' => ':attribute একটি তালিকা হতে হবে।',
    'lowercase' => ':attribute ছোট হাতের অক্ষরে হতে হবে।',
    'lt' => [
        'array' => ':attribute এ :value টির কম আইটেম থাকতে হবে।',
        'file' => ':attribute :value কিলোবাইটের কম হতে হবে।',
        'numeric' => ':attribute :value এর চেয়ে ছোট হতে হবে।',
        'string' => ':attribute :value অক্ষরের কম হতে হবে।',
    ],
    'lte' => [
        'array' => ':attribute এ :value টির বেশি আইটেম থাকতে পারবে না।',
        'file' => ':attribute :value কিলোবাইটের সমান বা কম হতে হবে।',
        'numeric' => ':attribute :value এর সমান বা ছোট হতে হবে।',
        'string' => ':attribute :value অক্ষরের সমান বা কম হতে হবে।',
    ],
    'mac_address' => ':attribute একটি সঠিক MAC ঠিকানা হতে হবে।',
    'max' => [
        'array' => ':attribute এ :max টির বেশি আইটেম থাকতে পারবে না।',
        'file' => ':attribute :max কিলোবাইটের বেশি হতে পারবে না।',
        'numeric' => ':attribute :max এর বেশি হতে পারবে না।',
        'string' => ':attribute :max অক্ষরের বেশি হতে পারবে না।',
    ],
    'max_digits' => ':attribute :max সংখ্যার বেশি হতে পারবে না।',
    'mimes' => ':attribute এই ধরনের ফাইল হতে হবে: :values।',
    'mimetypes' => ':attribute এই ধরনের ফাইল হতে হবে: :values।',
    'min' => [
        'array' => ':attribute এ অন্তত :min টি আইটেম থাকতে হবে।',
        'file' => ':attribute অন্তত :min কিলোবাইট হতে হবে।',
        'numeric' => ':attribute অন্তত :min হতে হবে।',
        'string' => ':attribute অন্তত :min অক্ষরের হতে হবে।',
    ],
    'min_digits' => ':attribute অন্তত :min সংখ্যার হতে হবে।',
    'missing' => ':attribute থাকা যাবে না।',
    'missing_if' => ':other যখন :value হয় তখন :attribute থাকা যাবে না।',
    'missing_unless' => ':other :value না হলে :attribute থাকা যাবে না।',
    'missing_with' => ':values থাকলে :attribute থাকা যাবে না।',
    'missing_with_all' => ':values সবগুলো থাকলে :attribute থাকা যাবে না।',
    'multiple_of' => ':attribute :value এর গুণিতক হতে হবে।',
    'not_in' => 'নির্বাচিত :attribute সঠিক নয়।',
    'not_regex' => ':attribute এর ফরম্যাট সঠিক নয়।',
    'numeric' => ':attribute একটি সংখ্যা হতে হবে।',
    'password' => [
        'letters' => ':attribute এ অন্তত একটি অক্ষর থাকতে হবে।',
        'mixed' => ':attribute এ অন্তত একটি বড় হাতের এবং একটি ছোট হাতের অক্ষর থাকতে হবে।',
        'numbers' => ':attribute এ অন্তত একটি সংখ্যা থাকতে হবে।',
        'symbols' => ':attribute এ অন্তত একটি চিহ্ন থাকতে হবে।',
        'uncompromised' => 'এই :attribute একটি তথ্য ফাঁসের ঘটনায় পাওয়া গেছে। অন্য একটি :attribute দিন।',
    ],
    'present' => ':attribute থাকতে হবে।',
    'present_if' => ':other যখন :value হয় তখন :attribute থাকতে হবে।',
    'present_unless' => ':other :value না হলে :attribute থাকতে হবে।',
    'present_with' => ':values থাকলে :attribute থাকতে হবে।',
    'present_with_all' => ':values সবগুলো থাকলে :attribute থাকতে হবে।',
    'prohibited' => ':attribute দেওয়া নিষিদ্ধ।',
    'prohibited_if' => ':other যখন :value হয় তখন :attribute দেওয়া নিষিদ্ধ।',
    'prohibited_if_accepted' => ':other গ্রহণ করা হলে :attribute দেওয়া নিষিদ্ধ।',
    'prohibited_if_declined' => ':other প্রত্যাখ্যান করা হলে :attribute দেওয়া নিষিদ্ধ।',
    'prohibited_unless' => ':other :values এর মধ্যে না থাকলে :attribute দেওয়া নিষিদ্ধ।',
    'prohibits' => ':attribute থাকলে :other দেওয়া যাবে না।',
    'regex' => ':attribute এর ফরম্যাট সঠিক নয়।',
    'required' => ':attribute দিতে হবে।',
    'required_array_keys' => ':attribute এ এই কীগুলো থাকতে হবে: :values।',
    'required_if' => ':other যখন :value হয় তখন :attribute দিতে হবে।',
    'required_if_accepted' => ':other গ্রহণ করা হলে :attribute দিতে হবে।',
    'required_if_declined' => ':other প্রত্যাখ্যান করা হলে :attribute দিতে হবে।',
    'required_unless' => ':other :values এর মধ্যে না থাকলে :attribute দিতে হবে।',
    'required_with' => ':values থাকলে :attribute দিতে হবে।',
    'required_with_all' => ':values সবগুলো থাকলে :attribute দিতে হবে।',
    'required_without' => ':values না থাকলে :attribute দিতে হবে।',
    'required_without_all' => ':values এর কোনোটিই না থাকলে :attribute দিতে হবে।',
    'same' => ':attribute এবং :other একই হতে হবে।',
    'size' => [
        'array' => ':attribute এ :size টি আইটেম থাকতে হবে।',
        'file' => ':attribute :size কিলোবাইট হতে হবে।',
        'numeric' => ':attribute :size হতে হবে।',
        'string' => ':attribute :size অক্ষরের হতে হবে।',
    ],
    'starts_with' => ':attribute নিচের কোনো একটি দিয়ে শুরু হতে হবে: :values।',
    'string' => ':attribute একটি লেখা হতে হবে।',
    'timezone' => ':attribute একটি সঠিক টাইমজোন হতে হবে।',
    'unique' => 'এই :attribute আগেই ব্যবহার করা হয়েছে।',
    'uploaded' => ':attribute আপলোড করা যায়নি।',
    'uppercase' => ':attribute বড় হাতের অক্ষরে হতে হবে।',
    'url' => ':attribute একটি সঠিক URL হতে হবে।',
    'ulid' => ':attribute একটি সঠিক ULID হতে হবে।',
    'uuid' => ':attribute একটি সঠিক UUID হতে হবে।',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Field names as the shop floor reads them. Add an entry here for every new
    | form field rather than writing a custom message.
    |
    */

    'attributes' => [
        'name' => 'নাম',
        'phone' => 'মোবাইল নম্বর',
        'alt_phone' => 'বিকল্প মোবাইল নম্বর',
        'email' => 'ইমেইল',
        'password' => 'পাসওয়ার্ড',
        'password_confirmation' => 'পাসওয়ার্ড নিশ্চিতকরণ',
        'current_password' => 'বর্তমান পাসওয়ার্ড',
        'address' => 'ঠিকানা',
        'area' => 'এলাকা',
        'shop_id' => 'দোকান',
        'is_active' => 'সক্রিয়',
    ],

];
