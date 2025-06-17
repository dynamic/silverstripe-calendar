<?php

namespace Dynamic\Calendar\Controller;

use Carbon\Carbon;
use Exception;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Class EventPageController
 * @package Dynamic\Calendar\Controller
 */
class EventPageController extends \PageController
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'occurrences',
        'next-occurrence',
    ];

    /**
     * Get event occurrences for a date range (AJAX endpoint)
     * 
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function occurrences(HTTPRequest $request): HTTPResponse
    {
        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'application/json');
        
        if (!$this->dataRecord->eventRecurs()) {
            $response->setBody(json_encode(['occurrences' => []]));
            return $response;
        }
        
        $fromDate = $request->getVar('from') ?: Carbon::now()->format('Y-m-d');
        $toDate = $request->getVar('to') ?: Carbon::now()->addMonths(3)->format('Y-m-d');
        
        try {
            $occurrences = [];
            $generator = $this->dataRecord->getOccurrences($fromDate, $toDate, 50); // Limit to 50 for UI
            
            foreach ($generator as $occurrence) {
                $occurrences[] = [
                    'title' => $occurrence->Title,
                    'date' => $occurrence->StartDate ? Carbon::parse($occurrence->StartDate)->format('M j, Y') : '',
                    'time' => $occurrence->StartTime ? Carbon::parse($occurrence->StartTime)->format('g:i A') : '',
                    'isModified' => $occurrence->isModified(),
                    'isDeleted' => $occurrence->isDeleted(),
                    'link' => $occurrence->Link ?? $this->dataRecord->Link(),
                ];
            }
            
            $response->setBody(json_encode(['occurrences' => $occurrences]));
        } catch (Exception $e) {
            $response->setStatusCode(500);
            $response->setBody(json_encode(['error' => 'Failed to load occurrences']));
        }
        
        return $response;
    }
    
    /**
     * Get next occurrence (AJAX endpoint)
     * 
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function nextOccurrence(HTTPRequest $request): HTTPResponse
    {
        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'application/json');
        
        if (!$this->dataRecord->eventRecurs()) {
            $response->setBody(json_encode(['nextOccurrence' => null]));
            return $response;
        }
        
        try {
            $nextOccurrence = $this->dataRecord->getNextOccurrence();
            
            if ($nextOccurrence) {
                $data = [
                    'nextOccurrence' => [
                        'date' => Carbon::parse($nextOccurrence->StartDate)->format('M j, Y'),
                        'time' => $nextOccurrence->StartTime ? Carbon::parse($nextOccurrence->StartTime)->format('g:i A') : null,
                        'isModified' => $nextOccurrence->isModified(),
                    ]
                ];
            } else {
                $data = ['nextOccurrence' => null];
            }
            
            $response->setBody(json_encode($data));
        } catch (Exception $e) {
            $response->setStatusCode(500);
            $response->setBody(json_encode(['error' => 'Failed to load next occurrence']));
        }
        
        return $response;
    }
    
    /**
     * Helper methods for templates
     */
    public function CurrentDate(): string
    {
        return Carbon::now()->format('Y-m-d');
    }
    
    public function CurrentFromDate(): string
    {
        return $this->getRequest()->getVar('from') ?: Carbon::now()->format('Y-m-d');
    }
    
    public function CurrentToDate(): string
    {
        return $this->getRequest()->getVar('to') ?: Carbon::now()->addMonths(3)->format('Y-m-d');
    }
}
