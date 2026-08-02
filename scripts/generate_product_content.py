#!/usr/bin/env python3
"""Generate database/data/product_content.php with unique SEO content."""
from __future__ import annotations

from pathlib import Path
from textwrap import dedent

OUT = Path(r"d:\Projects\toys\database\data\product_content.php")

PRODUCTS = [
    {
        "slug": "everyday-lunch-box-set",
        "name": "Everyday Lunch Box Set",
        "keyword": "lunch box set",
        "alt": "Everyday Lunch Box Set for school office and daily meals",
        "kind": "set",
        "color": None,
        "material": "Food-safe plastic and steel combo set",
        "use": "school, office, and everyday meal packing",
        "hook": "A compact matching lunch box set designed for people who pack meals every day without wanting bulky containers.",
        "angle": "The set keeps portions organised so dal, sabzi, roti, and a small side can travel together without mixing into one messy box.",
        "care": "Hand wash with mild soap, dry thoroughly before closing lids, and avoid abrasive scrubbers on printed surfaces.",
        "specs": [
            ("Product type", "Multi-piece lunch box set"),
            ("Best for", "Daily school and office tiffin"),
            ("Pack style", "Matching set for organised portions"),
            ("Care", "Hand wash recommended"),
            ("Brand", "Ventures Mart"),
            ("Fulfilment", "Ships in 2–5 days across India"),
            ("Replacement", "7-day replacement for damaged or incorrect items"),
        ],
        "features": [
            "Practical matching pieces for everyday meal packing",
            "Compact footprint that fits school and office bags",
            "Organised portions for roti, sabzi, and snacks",
            "Easy everyday cleaning routine",
            "Food-safe packing for Ventures Mart delivery",
            "7-day replacement support for damaged or incorrect boxes",
        ],
    },
    {
        "slug": "tokyo-3-compartment-steel-lunch-box-blue",
        "name": "Tokyo 3 Compartment Steel Lunch Box - Blue",
        "keyword": "steel lunch box blue",
        "alt": "Tokyo 3 Compartment Steel Lunch Box Blue for school and office",
        "kind": "steel",
        "color": "Blue",
        "material": "Food-grade stainless steel",
        "use": "balanced office and school meals with separated portions",
        "hook": "The Tokyo blue steel lunch box gives you three compartments so protein, carbs, and sides stay neat until lunchtime.",
        "angle": "Blue is easy to spot in a shared fridge or office bag, and the steel body stands up to daily commute wear better than flimsy plastic tiffins.",
        "care": "Wash with a soft sponge, dry completely, and store with lids slightly open when not in use to keep the steel fresh.",
        "specs": [
            ("Material", "Food-grade stainless steel"),
            ("Compartments", "3"),
            ("Colour", "Blue"),
            ("Best for", "School and office tiffin"),
            ("Care", "Hand wash and dry fully"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Three-compartment steel layout for neat portions",
            "Durable food-grade stainless steel body",
            "Blue finish that stands out in bags and fridges",
            "Practical everyday carry for school or office",
            "Easy to clean for daily reuse",
            "Backed by Ventures Mart packing and replacement support",
        ],
    },
    {
        "slug": "tokyo-3-compartment-steel-lunch-box-green",
        "name": "Tokyo 3 Compartment Steel Lunch Box - Green",
        "keyword": "steel lunch box green",
        "alt": "Tokyo 3 Compartment Steel Lunch Box Green for daily tiffin",
        "kind": "steel",
        "color": "Green",
        "material": "Food-grade stainless steel",
        "use": "fresh everyday Indian meals with separated sabzi and sides",
        "hook": "Choose the Tokyo green steel lunch box when you want compartment packing with a calm, everyday colour that feels fresh on the dining table and in the bag.",
        "angle": "Green suits shoppers who prefer softer tones than bold primary colours, while still getting the same three-section steel practicality for roti, dal, and salad.",
        "care": "Avoid leaving salty or acidic food overnight; rinse after use and dry the compartments before stacking.",
        "specs": [
            ("Material", "Food-grade stainless steel"),
            ("Compartments", "3"),
            ("Colour", "Green"),
            ("Best for", "Balanced daily meals"),
            ("Care", "Rinse after use and dry fully"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Three steel compartments for organised packing",
            "Fresh green colourway for everyday carry",
            "Sturdy stainless body for school and office use",
            "Helps keep sabzi, roti, and sides separated",
            "Simple wash-and-dry care routine",
            "Ships from Ventures Mart with replacement support",
        ],
    },
    {
        "slug": "tokyo-3-compartment-steel-lunch-box-pink",
        "name": "Tokyo 3 Compartment Steel Lunch Box - Pink",
        "keyword": "steel lunch box pink",
        "alt": "Tokyo 3 Compartment Steel Lunch Box Pink for school and office",
        "kind": "steel",
        "color": "Pink",
        "material": "Food-grade stainless steel",
        "use": "stylish daily tiffin packing without sacrificing durability",
        "hook": "The Tokyo pink steel lunch box pairs a cheerful colour with a serious three-compartment layout for people who want their tiffin to look personal.",
        "angle": "Pink is popular for gifting and for shoppers who want a lighter aesthetic, while stainless steel keeps the focus on food safety and daily durability.",
        "care": "Clean promptly after spicy or coloured foods, wipe the exterior gently, and dry lids and seals thoroughly.",
        "specs": [
            ("Material", "Food-grade stainless steel"),
            ("Compartments", "3"),
            ("Colour", "Pink"),
            ("Best for", "Daily tiffin and gifting"),
            ("Care", "Gentle wash; dry lids fully"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Cheerful pink finish with practical steel construction",
            "Three compartments for neat meal sections",
            "Food-grade stainless steel for everyday packing",
            "Gift-friendly look for family and friends",
            "Designed for school bags and office totes",
            "Ventures Mart delivery and 7-day replacement support",
        ],
    },
    {
        "slug": "bear-family-lunch-box",
        "name": "Bear Family Lunch Box",
        "keyword": "kids lunch box",
        "alt": "Bear Family Lunch Box for kids school snacks and meals",
        "kind": "kids",
        "color": None,
        "material": "Kids-friendly lunch box materials with cheerful print finish",
        "use": "school bags, snacks, and cheerful kids mealtime routines",
        "hook": "The Bear Family Lunch Box turns everyday packing into something kids actually look forward to opening at school.",
        "angle": "Parents shop this style when they need a fun visual cue that still works for roti, fruit, and small snacks without looking like an adult office tiffin.",
        "care": "Wash with mild detergent, avoid harsh scrubbing on the bear artwork, and dry before packing the next day.",
        "specs": [
            ("Audience", "Kids and school snacks"),
            ("Theme", "Bear family design"),
            ("Best for", "School bags and daily snacks"),
            ("Care", "Mild wash; protect printed artwork"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Cute bear-themed design kids enjoy using",
            "Sized for school bags and snack packing",
            "Encourages independent mealtime habits",
            "Practical everyday cleaning for parents",
            "Cheerful look without complicated parts",
            "Supported by Ventures Mart shipping and replacement",
        ],
    },
    {
        "slug": "delicious-steel-lunch-box",
        "name": "Delicious Steel Lunch Box",
        "keyword": "stainless steel lunch box",
        "alt": "Delicious Steel Lunch Box for everyday Indian tiffin packing",
        "kind": "steel",
        "color": None,
        "material": "Durable food-grade stainless steel",
        "use": "everyday Indian tiffin packing with a clean durable finish",
        "hook": "Delicious Steel Lunch Box is built for people who want a straightforward stainless steel tiffin without trendy extras getting in the way.",
        "angle": "The focus is durability and a clean finish that looks good on the desk after a commute, making it a reliable daily driver rather than a novelty box.",
        "care": "Wash after each use, dry the steel thoroughly, and avoid sudden extreme temperature shocks with empty containers.",
        "specs": [
            ("Material", "Food-grade stainless steel"),
            ("Finish", "Clean durable everyday finish"),
            ("Best for", "Daily Indian tiffin packing"),
            ("Care", "Wash and dry after every use"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Durable steel body for daily tiffin use",
            "Clean practical finish for office and home",
            "Food-safe stainless construction",
            "Simple packing for roti, sabzi, and sides",
            "Easy routine cleaning",
            "Ventures Mart fulfilment with replacement support",
        ],
    },
    {
        "slug": "koi-koi-steel-lunch-box-blue",
        "name": "Koi-Koi Steel Lunch Box - Blue",
        "keyword": "leak proof steel lunch box",
        "alt": "Koi-Koi Steel Lunch Box Blue with compartments and soup container",
        "kind": "koi",
        "color": "Blue",
        "material": "Stainless steel with leak-conscious clip design",
        "use": "meals that include gravy or soup alongside dry sides",
        "hook": "Koi-Koi in blue is for packed lunches that need more structure: compartments, a clear lid view, and a separate soup container for days with rasam or dal.",
        "angle": "Blue reads as calm and professional for office desks while the clip-and-lid setup aims to reduce spill anxiety during travel.",
        "care": "Clean clips and lid channels carefully, dry the soup container separately, and check seals before packing liquids.",
        "specs": [
            ("Material", "Stainless steel body"),
            ("Layout", "Multi-compartment with soup container"),
            ("Lid", "Clear lid with leak-conscious clips"),
            ("Colour", "Blue"),
            ("Best for", "Gravy meals and office tiffin"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Leak-conscious clips with clear lid visibility",
            "Separate soup container for liquid sides",
            "Blue steel finish for everyday office carry",
            "Compartment packing for mixed Indian meals",
            "Built for commute-friendly packing",
            "Ventures Mart packing and replacement support",
        ],
    },
    {
        "slug": "koi-koi-steel-lunch-box-pink",
        "name": "Koi-Koi Steel Lunch Box - Pink",
        "keyword": "steel lunch box with soup container",
        "alt": "Koi-Koi Steel Lunch Box Pink with soup container for office meals",
        "kind": "koi",
        "color": "Pink",
        "material": "Stainless steel with leak-conscious clip design",
        "use": "colourful yet practical packing for gravy-heavy lunches",
        "hook": "The pink Koi-Koi steel lunch box keeps the same compartment-plus-soup idea as the blue version, with a softer colour that feels personal on a crowded desk.",
        "angle": "Shoppers often choose pink for gifting or self-expression while still needing a serious steel lunch system for dal, rice, and a liquid side.",
        "care": "Rinse the soup cup immediately after use, wipe clips dry, and avoid leaving wet food sealed overnight.",
        "specs": [
            ("Material", "Stainless steel body"),
            ("Layout", "Multi-compartment with soup container"),
            ("Lid", "Clear lid with leak-conscious clips"),
            ("Colour", "Pink"),
            ("Best for", "Office meals with gravy"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Pink colourway with practical steel construction",
            "Dedicated soup container for liquids",
            "Clear lid for quick packing checks",
            "Clip closure designed for travel days",
            "Compartments for rice, sabzi, and sides",
            "Ships from Ventures Mart with replacement support",
        ],
    },
    {
        "slug": "koi-koi-steel-lunch-box-purple",
        "name": "Koi-Koi Steel Lunch Box - Purple",
        "keyword": "purple steel lunch box",
        "alt": "Koi-Koi Steel Lunch Box Purple for school and office tiffin",
        "kind": "koi",
        "color": "Purple",
        "material": "Stainless steel with leak-conscious clip design",
        "use": "standout colour packing for structured multi-part meals",
        "hook": "Purple Koi-Koi is the option when you want the compartment system to be easy to recognise in a shared fridge or bag pile.",
        "angle": "Beyond colour, the value is the packing layout: dry sections plus a soup cup so weekday Indian meals stay intentional instead of mixed.",
        "care": "Clean under the clips weekly, dry fully, and store with the lid ajar when empty.",
        "specs": [
            ("Material", "Stainless steel body"),
            ("Layout", "Multi-compartment with soup container"),
            ("Lid", "Clear lid with leak-conscious clips"),
            ("Colour", "Purple"),
            ("Best for", "Structured weekday meals"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Distinct purple finish for easy identification",
            "Soup container plus dry compartments",
            "Clear lid packing visibility",
            "Steel build for daily reuse",
            "Commute-friendly clip closure",
            "Ventures Mart delivery and replacement policy",
        ],
    },
    {
        "slug": "printed-steel-lunch-box-blue",
        "name": "Printed Steel Lunch Box - Blue",
        "keyword": "printed steel lunch box",
        "alt": "Printed Steel Lunch Box Blue for school and office meals",
        "kind": "printed",
        "color": "Blue",
        "material": "Printed stainless steel lunch box",
        "use": "everyday meals with a printed finish that feels less plain",
        "hook": "Printed Steel Lunch Box in blue adds pattern interest to a stainless steel tiffin without turning it into a fragile novelty item.",
        "angle": "Blue print styles work well for school and office because they look intentional while still reading as a practical steel lunch box.",
        "care": "Wash gently to preserve print quality, avoid harsh scrubbers, and dry before storage.",
        "specs": [
            ("Material", "Printed stainless steel"),
            ("Colour", "Blue"),
            ("Best for", "School and office meals"),
            ("Care", "Gentle wash to protect print"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Printed blue finish on a steel lunch box",
            "Everyday durability for school and office",
            "Food-oriented stainless construction",
            "Less plain than unprinted steel boxes",
            "Simple daily cleaning with print care",
            "Ventures Mart shipping across India",
        ],
    },
    {
        "slug": "printed-steel-lunch-box-pink",
        "name": "Printed Steel Lunch Box - Pink",
        "keyword": "printed lunch box pink",
        "alt": "Printed Steel Lunch Box Pink for daily school and office use",
        "kind": "printed",
        "color": "Pink",
        "material": "Printed stainless steel lunch box",
        "use": "personal style packing for weekday Indian meals",
        "hook": "The pink printed steel lunch box is for shoppers who want a softer visual style while keeping stainless steel as the packing core.",
        "angle": "Pink print variants are popular gifts and self-purchases when a plain steel box feels too corporate for the person carrying it.",
        "care": "Hand wash preferred for print longevity; dry lids and edges carefully after washing.",
        "specs": [
            ("Material", "Printed stainless steel"),
            ("Colour", "Pink"),
            ("Best for", "Daily school and office use"),
            ("Care", "Hand wash to protect print"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Soft pink printed steel aesthetic",
            "Practical weekday tiffin packing",
            "Stainless steel durability under the print",
            "Gift-friendly presentation",
            "Gentle-care cleaning routine",
            "Supported by Ventures Mart fulfilment",
        ],
    },
    {
        "slug": "printed-steel-lunch-box-purple",
        "name": "Printed Steel Lunch Box - Purple",
        "keyword": "printed steel lunch box purple",
        "alt": "Printed Steel Lunch Box Purple for school and office",
        "kind": "printed",
        "color": "Purple",
        "material": "Printed stainless steel lunch box",
        "use": "standout printed steel packing for daily meals",
        "hook": "Printed Steel Lunch Box Purple stands out in a bag with a richer colour story while staying grounded in stainless steel practicality.",
        "angle": "Purple print buyers usually want something less generic than silver steel, especially for school lockers and shared office spaces.",
        "care": "Clean promptly after turmeric-heavy meals, rinse print surfaces gently, and air dry fully.",
        "specs": [
            ("Material", "Printed stainless steel"),
            ("Colour", "Purple"),
            ("Best for", "School and office meals"),
            ("Care", "Gentle rinse and full dry"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Distinct purple printed finish",
            "Stainless steel core for daily packing",
            "Easy to spot in school bags and offices",
            "Practical for Indian weekday meals",
            "Print-aware cleaning guidance",
            "Ventures Mart delivery and replacement support",
        ],
    },
    {
        "slug": "safari-kids-steel-lunch-box-dino-green",
        "name": "Safari Kids Steel Lunch Box - Dino Green",
        "keyword": "kids steel lunch box",
        "alt": "Safari Kids Steel Lunch Box Dino Green for school meals",
        "kind": "safari",
        "color": "Dino Green",
        "material": "Kids steel lunch box with safari dino theme",
        "use": "school meals that kids are excited to open",
        "hook": "Dino Green from the Safari Kids steel lunch box line makes school packing feel like an adventure instead of a chore.",
        "angle": "Parents choose character themes because kids are more likely to finish meals when the box feels personal, while steel keeps the focus on durable everyday use.",
        "care": "Wash with mild soap, protect the dino artwork from abrasive pads, and dry before packing.",
        "specs": [
            ("Audience", "Kids school meals"),
            ("Theme", "Dino green safari design"),
            ("Material", "Kids steel lunch box"),
            ("Best for", "School bags"),
            ("Care", "Mild wash; protect artwork"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Dino green theme kids love",
            "Steel lunch box durability for school days",
            "Encourages better lunchtime habits",
            "Practical size for school bags",
            "Parent-friendly cleaning routine",
            "Ventures Mart shipping and replacement support",
        ],
    },
    {
        "slug": "safari-kids-steel-lunch-box-dino-yellow",
        "name": "Safari Kids Steel Lunch Box - Dino Yellow",
        "keyword": "dino lunch box for kids",
        "alt": "Safari Kids Steel Lunch Box Dino Yellow for school tiffin",
        "kind": "safari",
        "color": "Dino Yellow",
        "material": "Kids steel lunch box with safari dino theme",
        "use": "bright school tiffin packing for dinosaur fans",
        "hook": "Dino Yellow brings a brighter safari kids steel lunch box option for children who respond to bold colours at the lunch table.",
        "angle": "Yellow is easier to spot in a school bag scramble and pairs the same kids-steel practicality with a different mood than the green dino variant.",
        "care": "Rinse after sticky snacks, wipe exterior gently, and dry lids completely overnight.",
        "specs": [
            ("Audience", "Kids school tiffin"),
            ("Theme", "Dino yellow safari design"),
            ("Material", "Kids steel lunch box"),
            ("Best for", "Bright everyday school packing"),
            ("Care", "Gentle wipe and full dry"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Bright dino yellow character theme",
            "Steel durability for school routines",
            "Easy to find in busy school bags",
            "Fun presentation that supports mealtime",
            "Simple parent cleaning steps",
            "Backed by Ventures Mart fulfilment",
        ],
    },
    {
        "slug": "safari-kids-steel-lunch-box-chick-orange",
        "name": "Safari Kids Steel Lunch Box - Chick Orange",
        "keyword": "orange kids lunch box",
        "alt": "Safari Kids Steel Lunch Box Chick Orange for school snacks",
        "kind": "safari",
        "color": "Chick Orange",
        "material": "Kids steel lunch box with chick safari theme",
        "use": "playful school snacks and small meals",
        "hook": "Chick Orange is the softer character pick in the Safari Kids steel lunch box range for children who prefer cute themes over dinosaurs.",
        "angle": "Orange chick artwork keeps packing cheerful for younger kids, while steel construction remains the practical reason parents buy from Ventures Mart.",
        "care": "Use mild soap only on printed areas and air dry before sealing for the next school day.",
        "specs": [
            ("Audience", "Younger kids"),
            ("Theme", "Chick orange safari design"),
            ("Material", "Kids steel lunch box"),
            ("Best for", "School snacks and meals"),
            ("Care", "Mild soap; air dry"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Cute chick orange character design",
            "Steel lunch box built for school days",
            "Cheerful packing for younger kids",
            "Practical everyday cleaning",
            "Fits typical school bag routines",
            "Ventures Mart delivery and replacement support",
        ],
    },
    {
        "slug": "safari-kids-steel-lunch-box-owl-purple",
        "name": "Safari Kids Steel Lunch Box - Owl Purple",
        "keyword": "owl lunch box kids",
        "alt": "Safari Kids Steel Lunch Box Owl Purple for school meals",
        "kind": "safari",
        "color": "Owl Purple",
        "material": "Kids steel lunch box with owl safari theme",
        "use": "school meals with a calmer character theme",
        "hook": "Owl Purple gives Safari Kids shoppers a wiser, calmer character theme while keeping the same steel lunch box usefulness for school.",
        "angle": "Purple owl designs often appeal to kids who want characters without loud dinosaur energy, and parents still get durable steel packing.",
        "care": "Clean prints gently, dry thoroughly, and avoid stacking wet boxes.",
        "specs": [
            ("Audience", "Kids school meals"),
            ("Theme", "Owl purple safari design"),
            ("Material", "Kids steel lunch box"),
            ("Best for", "School bags and daily meals"),
            ("Care", "Gentle clean and dry"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Owl purple theme with calm character appeal",
            "Steel durability for school packing",
            "Encourages kids to open and finish lunch",
            "Parent-friendly care routine",
            "Practical everyday school bag fit",
            "Ships from Ventures Mart with replacement support",
        ],
    },
    {
        "slug": "wooden-building-blocks",
        "name": "Wooden Building Blocks",
        "keyword": "wooden building blocks",
        "alt": "Wooden Building Blocks for creative kids play at home",
        "kind": "toy",
        "color": None,
        "material": "Natural wood building blocks",
        "use": "open-ended creative construction play at home",
        "hook": "Wooden Building Blocks from Ventures Mart support screen-free play where kids stack, sort, and invent towers at their own pace.",
        "angle": "Parents choose wood blocks because the play value lasts longer than single-purpose toys and encourages fine motor skills without batteries.",
        "care": "Wipe with a dry or slightly damp cloth; avoid soaking wooden pieces and store dry.",
        "specs": [
            ("Product type", "Wooden building blocks"),
            ("Play style", "Open-ended construction"),
            ("Best for", "Creative kids play at home"),
            ("Care", "Dry wipe; do not soak"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement for damaged or incorrect items"),
        ],
        "features": [
            "Natural wood blocks for open-ended play",
            "Supports stacking, sorting, and imagination",
            "Screen-free creative activity at home",
            "Reusable play value beyond a single game",
            "Simple wipe-clean care",
            "Ventures Mart packing and replacement support",
        ],
    },
    {
        "slug": "mini-kitchen-play-set",
        "name": "Mini Kitchen Play Set",
        "keyword": "kids kitchen play set",
        "alt": "Mini Kitchen Play Set for imaginative pretend cooking play",
        "kind": "toy",
        "color": None,
        "material": "Kids pretend-play kitchen set materials",
        "use": "imaginative cooking roleplay and social play",
        "hook": "The Mini Kitchen Play Set invites kids into pretend cooking stories that build language, sharing, and sequencing skills.",
        "angle": "Roleplay kitchens are popular because they mirror adult routines kids observe daily, turning ordinary afternoons into collaborative play.",
        "care": "Wipe pieces clean, keep small accessories together, and store away from heavy moisture.",
        "specs": [
            ("Product type", "Pretend kitchen play set"),
            ("Play style", "Imaginative roleplay"),
            ("Best for", "Home play and group play"),
            ("Care", "Wipe clean; store dry"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Imaginative pretend cooking play",
            "Encourages sharing and storytelling",
            "Mirrors familiar kitchen routines",
            "Reusable roleplay value",
            "Simple wipe-clean upkeep",
            "Ventures Mart delivery across India",
        ],
    },
    {
        "slug": "soft-plush-buddy",
        "name": "Soft Plush Buddy",
        "keyword": "soft plush toy",
        "alt": "Soft Plush Buddy cuddle toy for kids comfort play",
        "kind": "toy",
        "color": None,
        "material": "Soft plush fabric buddy toy",
        "use": "comfort play, cuddles, and quiet-time companionship",
        "hook": "Soft Plush Buddy is the comfort companion kids reach for during quiet time, travel, and bedtime wind-downs.",
        "angle": "Plush toys remain a staple because emotional comfort is part of healthy play, not only learning toys and blocks.",
        "care": "Spot clean when possible; follow gentle wash guidance and air dry fully before returning to play.",
        "specs": [
            ("Product type", "Soft plush buddy"),
            ("Play style", "Comfort and cuddle play"),
            ("Best for", "Quiet time and travel comfort"),
            ("Care", "Spot clean; air dry"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Soft cuddle-ready plush companion",
            "Comfort play for quiet and travel moments",
            "Friendly everyday kids gift option",
            "Simple spot-clean care",
            "Lightweight for bags and outings",
            "Supported by Ventures Mart fulfilment",
        ],
    },
    {
        "slug": "color-pattern-tiles",
        "name": "Color Pattern Tiles",
        "keyword": "color pattern tiles kids",
        "alt": "Color Pattern Tiles educational play set for kids patterning",
        "kind": "toy",
        "color": None,
        "material": "Colourful patterning tiles for kids",
        "use": "colour recognition, patterning, and early learning play",
        "hook": "Color Pattern Tiles turn colour sorting into a hands-on game that supports early patterning and focus.",
        "angle": "Unlike passive toys, tiles invite kids to build sequences, match colours, and invent their own challenge levels as they grow.",
        "care": "Wipe clean after play, keep tiles together in one tray or pouch, and avoid extreme heat storage.",
        "specs": [
            ("Product type", "Colour pattern tiles"),
            ("Play style", "Early learning and patterning"),
            ("Best for", "Colour recognition and focus play"),
            ("Care", "Wipe clean; store together"),
            ("Brand", "Ventures Mart"),
            ("Delivery", "2–5 days"),
            ("Replacement", "7-day replacement window"),
        ],
        "features": [
            "Hands-on colour and pattern play",
            "Supports early learning focus",
            "Reusable challenge levels as kids grow",
            "Simple wipe-clean pieces",
            "Great independent or guided activity",
            "Ventures Mart shipping and replacement support",
        ],
    },
]


def php_str(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def word_count(html: str) -> int:
    import re

    text = re.sub(r"<[^>]+>", " ", html)
    return len([w for w in text.split() if w])


def build_description(p: dict) -> str:
    name = p["name"]
    color_bit = f" in {p['color']}" if p["color"] else ""
    paragraphs = [
        f"<p>{p['hook']} At Ventures Mart, {name} is listed for shoppers who want a clear product story, practical daily use, and trustworthy fulfilment across India.</p>",
        f"<p>{p['angle']} Whether you are packing for {p['use']}, the goal is the same: a product that feels intentional on day one and still useful months later.</p>",
        f"<p>Material and build matter for repeat use. {name} uses {p['material']}, chosen to match how Indian households actually pack, play, and clean. That means fewer throwaway compromises and more confidence when the item is used every weekday.</p>",
        f"<p>From a buying perspective, customers compare colour, layout, and durability before they commit. {name}{color_bit} is differentiated within the Ventures Mart catalogue so you can match the exact look and packing style you need instead of settling for a generic option.</p>",
        f"<p>Care is straightforward: {p['care']} Following a simple routine keeps the product looking presentable and ready for the next school day, office commute, or play session.</p>",
        f"<p>Ordering from Ventures Mart includes food-safe packing where relevant, typical delivery in 2–5 days, and a 7-day replacement window for damaged or incorrect items. If you are comparing options, review the features and specifications below, then explore related products in the same category to keep shopping connected across the site.</p>",
        f"<p>Searchers looking for {p['keyword']} often want clarity on use cases, care, and what makes one listing different from another. This page for {name} is written to answer those questions directly so you can decide quickly and buy with confidence.</p>",
    ]
    html = "".join(paragraphs)
    # Pad if under 300 words with an extra unique closing paragraph
    if word_count(html) < 300:
        html += (
            f"<p>Finally, {name} is part of Ventures Mart’s broader commitment to useful everyday products "
            f"with transparent product pages. Read the FAQs for shipping and care details, check related items "
            f"for alternate colours or styles, and use the specifications table to confirm fit before checkout. "
            f"A clear product page helps both shoppers and search engines understand what this listing offers, "
            f"which is why we invest in unique descriptions instead of thin one-line summaries.</p>"
        )
    return html


def build_faqs(p: dict) -> list[dict]:
    name = p["name"]
    faqs = [
        {
            "question": f"What is {name} best used for?",
            "answer": f"{name} is designed for {p['use']}. Check the features and specifications on this page for layout and care details before ordering from Ventures Mart.",
        },
        {
            "question": f"How should I clean and care for {name}?",
            "answer": p["care"] + " Consistent care helps the product stay ready for daily reuse.",
        },
        {
            "question": "How long does delivery take?",
            "answer": "Ventures Mart typically delivers across India in 2–5 days after dispatch, depending on your location and courier timelines.",
        },
        {
            "question": "What is the replacement policy?",
            "answer": "If your order arrives damaged or incorrect, Ventures Mart offers a 7-day replacement window. Contact support with your order details and clear photos when possible.",
        },
    ]
    if p["kind"] in {"steel", "koi", "printed", "safari", "set", "kids"}:
        faqs.append(
            {
                "question": "Is this lunch box suitable for school and office bags?",
                "answer": f"Yes. {name} is positioned for everyday carry in school and office bags. Use the compartment and size details in the specifications to confirm it matches how you pack meals.",
            }
        )
    else:
        faqs.append(
            {
                "question": "Is this toy suitable for home play?",
                "answer": f"Yes. {name} is intended for supervised home play. Review age-appropriate use with your child and store pieces safely after playtime.",
            }
        )
    return faqs[:5]


def meta_title(p: dict) -> str:
    base = f"{p['name']} | Ventures Mart"
    if len(base) <= 60:
        return base
    return f"{p['name'][:45]} | Ventures Mart"


def meta_description(p: dict) -> str:
    text = (
        f"Shop {p['name']} at Ventures Mart. Built for {p['use']}. "
        f"Delivery in 2–5 days with 7-day replacement support."
    )
    if len(text) > 165:
        text = text[:162].rstrip() + "..."
    if len(text) < 80:
        text += " View features, specifications, and FAQs."
    return text


def emit_entry(p: dict) -> str:
    desc = build_description(p)
    wc = word_count(desc)
    assert 300 <= wc <= 800, f"{p['slug']} word count {wc}"

    lines = [f"    {php_str(p['slug'])} => ["]
    lines.append(f"        'description' => {php_str(desc)},")
    lines.append("        'details' => [")
    for feature in p["features"]:
        lines.append(f"            {php_str(feature)},")
    lines.append("        ],")
    lines.append("        'specifications' => [")
    for label, value in p["specs"]:
        lines.append(
            "            ['label' => "
            + php_str(label)
            + ", 'value' => "
            + php_str(value)
            + "],"
        )
    lines.append("        ],")
    lines.append("        'seo' => [")
    lines.append(f"            'focus_keyword' => {php_str(p['keyword'])},")
    lines.append(f"            'image_alt_text' => {php_str(p['alt'])},")
    lines.append(f"            'meta_title' => {php_str(meta_title(p))},")
    lines.append(f"            'meta_description' => {php_str(meta_description(p))},")
    lines.append("        ],")
    lines.append("        'faqs' => [")
    for faq in build_faqs(p):
        lines.append(
            "            ['question' => "
            + php_str(faq["question"])
            + ", 'answer' => "
            + php_str(faq["answer"])
            + "],"
        )
    lines.append("        ],")
    lines.append("    ],")
    return "\n".join(lines)


def main() -> None:
    assert len(PRODUCTS) == 20
    body = "\n".join(emit_entry(p) for p in PRODUCTS)
    content = dedent(
        """\
        <?php

        declare(strict_types=1);

        /**
         * Unique product content enrichment payload keyed by slug.
         *
         * @return array<string, array{
         *     description: string,
         *     details: list<string>,
         *     specifications: list<array{label: string, value: string}>,
         *     seo: array<string, string>,
         *     faqs: list<array{question: string, answer: string}>
         * }>
         */
        return [
        """
    )
    # Fix indentation of return array - write manually
    content = (
        "<?php\n\n"
        "declare(strict_types=1);\n\n"
        "/**\n"
        " * Unique product content enrichment payload keyed by slug.\n"
        " *\n"
        " * @return array<string, array{\n"
        " *     description: string,\n"
        " *     details: list<string>,\n"
        " *     specifications: list<array{label: string, value: string}>,\n"
        " *     seo: array<string, string>,\n"
        " *     faqs: list<array{question: string, answer: string}>\n"
        " * }>\n"
        " */\n"
        "return [\n"
        f"{body}\n"
        "];\n"
    )
    OUT.write_text(content, encoding="utf-8")
    print(f"Wrote {OUT} ({OUT.stat().st_size} bytes)")
    for p in PRODUCTS:
        print(p["slug"], word_count(build_description(p)), "words")


if __name__ == "__main__":
    main()
