#!/usr/bin/env php
<?php

/**
 * ICS Validation Script
 * 
 * This script validates ICS content for RFC 5545 compliance
 * Usage: php validate_ics.php <ics_content_or_url>
 */

function validateICS($content) {
    $issues = [];
    $warnings = [];
    
    // Required components
    if (!preg_match('/BEGIN:VCALENDAR/', $content)) {
        $issues[] = "Missing required BEGIN:VCALENDAR";
    }
    
    if (!preg_match('/END:VCALENDAR/', $content)) {
        $issues[] = "Missing required END:VCALENDAR";
    }
    
    if (!preg_match('/VERSION:2\.0/', $content)) {
        $issues[] = "Missing required VERSION:2.0";
    }
    
    if (!preg_match('/PRODID:/', $content)) {
        $issues[] = "Missing required PRODID";
    }
    
    // Check for events
    $eventCount = preg_match_all('/BEGIN:VEVENT/', $content);
    $eventEndCount = preg_match_all('/END:VEVENT/', $content);
    
    if ($eventCount !== $eventEndCount) {
        $issues[] = "Mismatched BEGIN:VEVENT and END:VEVENT count";
    }
    
    echo "📊 ICS Validation Report\n";
    echo str_repeat("=", 50) . "\n";
    echo "Events found: {$eventCount}\n";
    
    // Validate individual events
    preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/s', $content, $events);
    
    foreach ($events[0] as $i => $event) {
        $eventNum = $i + 1;
        echo "\n🎯 Event {$eventNum}:\n";
        
        // Check required event properties
        $eventIssues = [];
        
        if (!preg_match('/UID:/', $event)) {
            $eventIssues[] = "Missing UID";
        }
        
        if (!preg_match('/DTSTAMP:/', $event)) {
            $eventIssues[] = "Missing DTSTAMP";
        }
        
        if (!preg_match('/SUMMARY:/', $event)) {
            $eventIssues[] = "Missing SUMMARY";
        }
        
        if (!preg_match('/DTSTART[;:]/', $event)) {
            $eventIssues[] = "Missing DTSTART";
        }
        
        // Check date formats
        if (preg_match('/DTSTART:(\d{8}T\d{6}Z?)/', $event, $matches)) {
            $dateFormat = $matches[1];
            if (!preg_match('/\d{8}T\d{6}Z?/', $dateFormat)) {
                $eventIssues[] = "Invalid DTSTART format: {$dateFormat}";
            }
        }
        
        if (preg_match('/DTEND:(\d{8}T\d{6}Z?)/', $event, $matches)) {
            $dateFormat = $matches[1];
            if (!preg_match('/\d{8}T\d{6}Z?/', $dateFormat)) {
                $eventIssues[] = "Invalid DTEND format: {$dateFormat}";
            }
        }
        
        // Extract event details for display
        preg_match('/SUMMARY:(.*)/', $event, $summaryMatch);
        $summary = $summaryMatch[1] ?? 'No title';
        
        preg_match('/DTSTART[;:](.*)/', $event, $startMatch);
        $start = $startMatch[1] ?? 'No start date';
        
        echo "  Title: {$summary}\n";
        echo "  Start: {$start}\n";
        
        if ($eventIssues) {
            echo "  ❌ Issues: " . implode(", ", $eventIssues) . "\n";
        } else {
            echo "  ✅ Valid\n";
        }
    }
    
    // Check for common issues
    if (preg_match('/[^\r\n][\r\n]/', $content)) {
        $warnings[] = "Lines should end with CRLF (\\r\\n)";
    }
    
    // Line length check (RFC recommends max 75 characters)
    $lines = explode("\n", $content);
    $longLines = 0;
    foreach ($lines as $line) {
        if (strlen(rtrim($line, "\r")) > 75) {
            $longLines++;
        }
    }
    
    if ($longLines > 0) {
        $warnings[] = "{$longLines} lines exceed 75 characters (RFC recommendation)";
    }
    
    // Summary
    echo "\n📋 Summary:\n";
    echo str_repeat("-", 30) . "\n";
    
    if (empty($issues)) {
        echo "✅ ICS structure is valid!\n";
    } else {
        echo "❌ Issues found:\n";
        foreach ($issues as $issue) {
            echo "  - {$issue}\n";
        }
    }
    
    if (!empty($warnings)) {
        echo "\n⚠️  Warnings:\n";
        foreach ($warnings as $warning) {
            echo "  - {$warning}\n";
        }
    }
    
    return empty($issues);
}

function fetchURL($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'ICS-Validator/1.0'
        ]
    ]);
    
    $content = @file_get_contents($url, false, $context);
    
    if ($content === false) {
        echo "❌ Error: Could not fetch URL: {$url}\n";
        return false;
    }
    
    return $content;
}

// Main execution
if ($argc < 2) {
    echo "Usage: php validate_ics.php <ics_content_or_url>\n";
    echo "Examples:\n";
    echo "  php validate_ics.php 'BEGIN:VCALENDAR...'\n";
    echo "  php validate_ics.php http://example.com/calendar.ics\n";
    echo "  php validate_ics.php /path/to/calendar.ics\n";
    exit(1);
}

$input = $argv[1];

echo "🔍 ICS Validator\n";
echo str_repeat("=", 50) . "\n";

// Determine input type
if (filter_var($input, FILTER_VALIDATE_URL)) {
    echo "📥 Fetching from URL: {$input}\n";
    $content = fetchURL($input);
    if ($content === false) {
        exit(1);
    }
} elseif (file_exists($input)) {
    echo "📁 Reading from file: {$input}\n";
    $content = file_get_contents($input);
} else {
    echo "📝 Parsing direct input\n";
    $content = $input;
}

if (empty($content)) {
    echo "❌ Error: No content to validate\n";
    exit(1);
}

echo "📊 Content size: " . strlen($content) . " bytes\n";
echo "\n";

$isValid = validateICS($content);

exit($isValid ? 0 : 1);