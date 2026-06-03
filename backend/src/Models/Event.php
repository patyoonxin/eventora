<?php
namespace App\Models;

use App\Models\Database;
use PDO;

class Event {
    // Fetch all active, approved events to show to students
    public static function getAllApproved(): array {
        $db = Database::connect();
        
        // We JOIN with the societies table so the student can see which club is hosting the event
        $query = "
            SELECT e.*, s.name AS society_name, s.faculty 
            FROM events e
            JOIN societies s ON e.society_id = s.id
            WHERE e.status = 'approved'
            ORDER BY e.starts_at ASC
        ";
        
        $stmt = $db->query($query);
        return $stmt->fetchAll();
    }

    public static function getUpcomingApproved()
    {
        $db = Database::connect();
        
        $query = "
            SELECT e.*, s.name AS society_name, s.faculty 
            FROM events e
            JOIN societies s ON e.society_id = s.id
            WHERE e.status = 'approved' 
            AND e.starts_at >= NOW()
            ORDER BY e.starts_at ASC
        ";
        
        $stmt = $db->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}