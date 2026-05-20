<?php

function getPartnershipCampaignStatusLabel($status) {
    $status = (int) $status;
    $labels = [
        0 => 'Pending review',
        1 => 'Active',
        2 => 'Paused',
        3 => 'Cancelled',
        4 => 'Awaiting payment',
    ];
    return $labels[$status] ?? 'Unknown';
}

function getPartnershipCampaignStatusClass($status) {
    $status = (int) $status;
    switch ($status) {
        case 1:  return 'badge-success';     
        case 0:
        case 4:  return 'badge-warning';     
        case 2:  return 'badge-secondary';   
        case 3:  return 'badge-danger';       
        default: return 'badge-secondary';
    }
}

function getOfferStatusLabel($status) {
    $status = (int) $status;
    $labels = [
        0 => 'Active',
        1 => 'Archived',
        2 => 'Sold',
        3 => 'Inactive',
    ];
    return $labels[$status] ?? 'Unknown';
}

function getOfferStatusClass($status) {
    $status = (int) $status;
    switch ($status) {
        case 0:  return 'badge-success';    
        case 1:  return 'badge-secondary'; 
        case 2:  return 'badge-info';       
        case 3:  return 'badge-warning';     
        default: return 'badge-secondary';
    }
}
