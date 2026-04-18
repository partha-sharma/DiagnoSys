<?php

function test_category_options(): array
{
    return ['Laboratory', 'Cardiology', 'Imaging', 'General'];
}

function sample_requirement_options(): array
{
    return ['Blood', 'Urine', 'Stool', 'Saliva', 'Swab', 'None'];
}

function normalize_test_category(string $value): string
{
    $value = trim($value);
    return in_array($value, test_category_options(), true) ? $value : 'General';
}

function normalize_sample_requirement(string $value): string
{
    $value = trim($value);
    return in_array($value, sample_requirement_options(), true) ? $value : 'None';
}

function infer_test_category_from_text(string $testName, string $description = '', string $legacyType = ''): string
{
    $haystack = strtolower(trim($testName . ' ' . $description . ' ' . $legacyType));

    if (
        str_contains($haystack, 'ecg') ||
        str_contains($haystack, 'echo') ||
        str_contains($haystack, 'echocardiogram') ||
        str_contains($haystack, 'troponin') ||
        str_contains($haystack, 'blood pressure') ||
        str_contains($haystack, 'cardio') ||
        str_contains($haystack, 'heart')
    ) {
        return 'Cardiology';
    }

    if (
        str_contains($haystack, 'x-ray') ||
        str_contains($haystack, 'xray') ||
        str_contains($haystack, 'ct') ||
        str_contains($haystack, 'mri') ||
        str_contains($haystack, 'ultrasound') ||
        str_contains($haystack, 'radiology') ||
        str_contains($haystack, 'imaging')
    ) {
        return 'Imaging';
    }

    if (
        str_contains($haystack, 'laboratory') ||
        str_contains($haystack, 'lab ') ||
        str_contains($haystack, 'blood test') ||
        str_contains($haystack, 'urine test')
    ) {
        return 'Laboratory';
    }

    if (
        str_contains($haystack, 'blood') ||
        str_contains($haystack, 'urine') ||
        str_contains($haystack, 'stool') ||
        str_contains($haystack, 'saliva') ||
        str_contains($haystack, 'swab') ||
        str_contains($haystack, 'thyroid') ||
        str_contains($haystack, 'cbc') ||
        str_contains($haystack, 'crp') ||
        str_contains($haystack, 'testosterone') ||
        str_contains($haystack, 'creatinine') ||
        str_contains($haystack, 'lipid')
    ) {
        return 'Laboratory';
    }

    return 'General';
}

function infer_sample_requirement_from_text(string $testName, string $description = '', string $testCategory = 'General'): string
{
    $haystack = strtolower(trim($testName . ' ' . $description));

    if (str_contains($haystack, 'urine')) {
        return 'Urine';
    }
    if (str_contains($haystack, 'stool')) {
        return 'Stool';
    }
    if (str_contains($haystack, 'saliva')) {
        return 'Saliva';
    }
    if (str_contains($haystack, 'swab')) {
        return 'Swab';
    }
    if (str_contains($haystack, 'blood') || str_contains($haystack, 'cbc') || str_contains($haystack, 'crp') || str_contains($haystack, 'lipid') || str_contains($haystack, 'testosterone') || str_contains($haystack, 'troponin') || str_contains($haystack, 'thyroid')) {
        return 'Blood';
    }

    if ($testCategory === 'Cardiology' || $testCategory === 'Imaging') {
        return 'None';
    }

    if ($testCategory === 'Laboratory') {
        return 'Blood';
    }

    return 'None';
}

function sample_requirement_display_label(string $sample): string
{
    return $sample === 'None' ? 'No Sample Required' : ($sample . ' Sample');
}

function technician_specialization_options(): array
{
    return ['Laboratory', 'Cardiology', 'Imaging'];
}

function normalize_technician_specialization(string $value): string
{
    $value = trim($value);

    if (in_array($value, technician_specialization_options(), true)) {
        return $value;
    }

    $haystack = strtolower($value);
    if (
        str_contains($haystack, 'cardio') ||
        str_contains($haystack, 'echo') ||
        str_contains($haystack, 'heart') ||
        str_contains($haystack, 'ecg')
    ) {
        return 'Cardiology';
    }

    if (
        str_contains($haystack, 'x-ray') ||
        str_contains($haystack, 'xray') ||
        str_contains($haystack, 'mri') ||
        str_contains($haystack, 'ct') ||
        str_contains($haystack, 'ultrasound') ||
        str_contains($haystack, 'radiology') ||
        str_contains($haystack, 'imaging')
    ) {
        return 'Imaging';
    }

    return 'Laboratory';
}

function appointment_test_categories(array $tests): array
{
    $categories = [];

    foreach ($tests as $test) {
        $category = normalize_test_category((string)($test['test_category'] ?? ''));
        if ($category === 'General') {
            $category = infer_test_category_from_text((string)($test['test_name'] ?? ''), (string)($test['description'] ?? ''), (string)($test['test_type'] ?? ''));
        }

        if ($category !== 'General') {
            $categories[$category] = true;
        }
    }

    return array_keys($categories);
}
