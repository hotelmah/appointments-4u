<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminResource extends JsonResource
{
    /**
     * Transform the admin resource into an array (CI3 API format compatibility).
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'email' => $this->email,
            'mobile' => $this->mobile_phone_number,
            'phone' => $this->work_phone_number,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip_code,
            'timezone' => $this->timezone,
            'language' => $this->language,
            'notes' => $this->notes,
            'ldapDn' => $this->ldap_dn,
            'roleId' => $this->id_roles,
            'settings' => [
                'username' => $this->settings->username ?? '',
                'notifications' => $this->settings->notifications ?? true,
                'calendarView' => $this->settings->calendar_view ?? 'default',
            ],
        ];
    }
}
