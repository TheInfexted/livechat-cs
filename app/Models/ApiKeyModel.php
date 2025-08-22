<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiKeyModel extends Model
{
    protected $table = 'api_keys';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    
    protected $allowedFields = [
        'key_id', 'api_key', 'client_name', 'client_email', 'client_domain',
        'status', 'last_used_at'
    ];
    
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    
    public function generateApiKey()
    {
        return 'lc_' . bin2hex(random_bytes(24)); // lc_1234567890abcdef...
    }
    
    public function generateKeyId()
    {
        return 'key_' . bin2hex(random_bytes(16));
    }
    
    public function validateApiKey($apiKey, $domain = null)
    {
        $key = $this->where('api_key', $apiKey)
                   ->where('status', 'active')
                   ->first();
        
        if (!$key) {
            return ['valid' => false, 'error' => 'Invalid or inactive API key'];
        }
        
        // Check domain restriction if set
        if ($key['client_domain'] && $domain) {
            if (!$this->isDomainAllowed($key['client_domain'], $domain)) {
                return ['valid' => false, 'error' => 'Domain not authorized for this API key'];
            }
        }
        
        // Update last used timestamp
        $this->update($key['id'], ['last_used_at' => date('Y-m-d H:i:s')]);
        
        return ['valid' => true, 'key_data' => $key];
    }
    
    public function isDomainAllowed($allowedDomains, $requestDomain)
    {
        $domains = array_map('trim', explode(',', $allowedDomains));
        
        foreach ($domains as $domain) {
            // Exact match
            if ($domain === $requestDomain) return true;
            
            // Wildcard subdomain match (*.example.com)
            if (str_starts_with($domain, '*.')) {
                $baseDomain = substr($domain, 2);
                if (str_ends_with($requestDomain, '.' . $baseDomain) || $requestDomain === $baseDomain) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    public function getApiKeyStats()
    {
        return $this->db->table('api_key_stats')->get()->getResultArray();
    }
    
    public function revokeApiKey($keyId)
    {
        return $this->update($keyId, ['status' => 'revoked']);
    }
    
    public function suspendApiKey($keyId)
    {
        return $this->update($keyId, ['status' => 'suspended']);
    }
    
    public function activateApiKey($keyId)
    {
        return $this->update($keyId, ['status' => 'active']);
    }
}
