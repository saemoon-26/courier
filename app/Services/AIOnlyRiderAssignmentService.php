<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class AIOnlyRiderAssignmentService
{
    public function assignParcels()
    {
        $pythonScript = base_path('ai_rider_assignment.py');
        
        try {
            // Use shell_exec for better compatibility
            $command = "python \"" . $pythonScript . "\"";
            $output = shell_exec($command . ' 2>&1');
            
            \Log::info('AI Script Output: ' . $output);
            
            $result = json_decode($output, true);
            
            if ($result && isset($result['assigned'])) {
                return $result;
            }
            
            return [
                'assigned' => 0,
                'error' => $output,
                'message' => 'Failed to parse AI script output'
            ];
        } catch (\Exception $e) {
            \Log::error('AI Script Error: ' . $e->getMessage());
            return [
                'assigned' => 0,
                'error' => $e->getMessage(),
                'message' => 'Failed to run AI assignment'
            ];
        }
    }

    public function assignBestRiderWithAI($pickupAddress = null, $pickupCity = null)
    {
        return $this->assignParcels();
    }
}
