<?php
/**
 * ISAMS API Integration Service
 * Handles all communication with ISAMS API
 */
class ISAMSService {
    private $apiKey;
    private $apiUrl;
    private $schoolCode;
    
    public function __construct() {
        $this->apiKey = ISAMS_API_KEY;
        $this->apiUrl = ISAMS_API_URL;
        $this->schoolCode = ISAMS_SCHOOL_CODE;
    }
    
    /**
     * Validate parent token and get parent data from ISAMS
     * @param string $token The SSO token from MSP
     * @return array|false Parent data or false if invalid
     */
    public function validateParentToken($token) {
        // Validate token format
        if (!$this->isValidTokenFormat($token)) {
            error_log("Invalid token format: $token");
            return false;
        }
        
        // Decode token to get parent identifier
        $tokenData = $this->decodeToken($token);
        if (!$tokenData) {
            error_log("Failed to decode token");
            return false;
        }
        
        // Verify token hasn't expired (5 minute window)
        if ($this->isTokenExpired($tokenData)) {
            error_log("Token expired");
            return false;
        }
        
        // Get parent data from ISAMS API
        $parentData = $this->getParentFromISAMS($tokenData['parent_id']);
        
        return $parentData;
    }
    
    /**
     * Get parent data from ISAMS API
     * @param string $parentId ISAMS parent ID
     * @return array|false Parent data or false if not found
     */
    public function getParentFromISAMS($parentId) {
        $endpoint = "{$this->apiUrl}/parents/{$parentId}";
        
        $response = $this->makeAPIRequest($endpoint);
        
        if (!$response || !isset($response['success']) || !$response['success']) {
            return false;
        }
        
        return [
            'isams_id' => $response['data']['id'],
            'email' => $response['data']['email'],
            'name' => $response['data']['name'],
            'phone' => $response['data']['phone'] ?? null,
            'children' => $this->getParentChildren($parentId)
        ];
    }
    
    /**
     * Get children for a parent from ISAMS
     * @param string $parentId ISAMS parent ID
     * @return array Array of children
     */
    public function getParentChildren($parentId) {
        $endpoint = "{$this->apiUrl}/parents/{$parentId}/children";
        
        $response = $this->makeAPIRequest($endpoint);
        
        if (!$response || !isset($response['success']) || !$response['success']) {
            return [];
        }
        
        return array_map(function($child) {
            return [
                'isams_id' => $child['id'],
                'name' => $child['name'],
                'grade' => $child['year_group'] ?? null,
                'class' => $child['form'] ?? null
            ];
        }, $response['data'] ?? []);
    }
    
    /**
     * Make API request to ISAMS
     * @param string $endpoint Full endpoint URL
     * @return array|false Response data or false on failure
     */
    private function makeAPIRequest($endpoint) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'X-School-Code: ' . $this->schoolCode
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("ISAMS API Error: HTTP $httpCode - $response");
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Decode and validate SSO token
     * @param string $token Encoded token from MSP
     * @return array|false Decoded token data or false
     */
    private function decodeToken($token) {
        try {
            // Token format: base64(parent_id|timestamp|signature)
            $decoded = base64_decode($token);
            $parts = explode('|', $decoded);
            
            if (count($parts) !== 3) {
                return false;
            }
            
            list($parentId, $timestamp, $signature) = $parts;
            
            // Verify signature
            $expectedSignature = hash_hmac('sha256', 
                $parentId . '|' . $timestamp, 
                ISAMS_SSO_SECRET
            );
            
            if (!hash_equals($expectedSignature, $signature)) {
                error_log("Invalid token signature");
                return false;
            }
            
            return [
                'parent_id' => $parentId,
                'timestamp' => (int)$timestamp
            ];
            
        } catch (Exception $e) {
            error_log("Token decode error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if token is expired
     * @param array $tokenData Decoded token data
     * @return bool True if expired
     */
    private function isTokenExpired($tokenData) {
        $tokenAge = time() - $tokenData['timestamp'];
        return $tokenAge > 300; // 5 minutes
    }
    
    /**
     * Validate token format
     * @param string $token Token to validate
     * @return bool True if valid format
     */
    private function isValidTokenFormat($token) {
        // Check if token is base64 encoded and reasonable length
        return !empty($token) && 
               strlen($token) > 20 && 
               strlen($token) < 500 &&
               base64_decode($token, true) !== false;
    }
}