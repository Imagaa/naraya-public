<?php

/*
 * Storefront search. Each synonym group is a set of single words that mean
 * the same thing to a buyer; searching any of them also finds the others.
 * Product names mix Indonesian and English (Open Food Facts), so most
 * groups pair the two.
 */
return [
    'synonyms' => [
        ['mie', 'mi', 'mee', 'noodle', 'noodles'],
        ['susu', 'milk'],
        ['teh', 'tea'],
        ['kopi', 'coffee'],
        ['gula', 'sugar'],
        ['garam', 'salt'],
        ['minyak', 'oil'],
        ['beras', 'rice'],
        ['air', 'water'],
        ['sabun', 'soap'],
        ['sampo', 'shampo', 'shampoo'],
        ['cokelat', 'coklat', 'chocolate', 'choco'],
        ['keju', 'cheese'],
        ['saus', 'saos', 'sauce'],
        ['biskuit', 'biscuit', 'biscuits', 'cookies'],
        ['kacang', 'peanut', 'peanuts'],
        ['jahe', 'ginger'],
        ['stroberi', 'strawberry'],
        ['vanila', 'vanilla'],
        ['popok', 'diapers'],
    ],
];
