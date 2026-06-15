<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Event;
use PDO;

class AnalyticsController
{
    public function getSocietyAnalytics(Request $request, Response $response): Response
{
    try {
        $db = \App\Models\Database::connect();
        $user = $request->getAttribute('user');

        // IMPORTANT: make sure this is correct in your JWT payload
        $societyId = $user->id;

        if (!$societyId) {
            $response->getBody()->write(json_encode([
                "status" => "error",
                "message" => "Unauthorized."
            ]));
            return $response->withStatus(403)
                ->withHeader('Content-Type', 'application/json');
        }

        // ======================================================
        // SUMMARY (ONLY APPROVED EVENTS)
        // ======================================================
        $summaryQuery = "
            SELECT 
                COUNT(DISTINCT e.id) AS total_events,
                COALESCE(SUM(e.price), 0) AS total_revenue
            FROM events e
            WHERE e.society_id = :society_id
            AND e.status = 'approved'
        ";

        $stmtSummary = $db->prepare($summaryQuery);
        $stmtSummary->execute(['society_id' => $societyId]);
        $summaryData = $stmtSummary->fetch(\PDO::FETCH_ASSOC);

        // ======================================================
        // REVENUE OVER TIME (ONLY APPROVED EVENTS)
        // ======================================================
        $revenueQuery = "
            SELECT 
                DATE(e.starts_at) AS event_date,
                e.title AS event_title,
                COALESCE(SUM(e.price), 0) AS revenue
            FROM events e
            WHERE e.society_id = :society_id
            AND e.status = 'approved'
            GROUP BY e.id
            ORDER BY e.starts_at ASC
        ";

        $stmt1 = $db->prepare($revenueQuery);
        $stmt1->execute(['society_id' => $societyId]);
        $revenueData = $stmt1->fetchAll(\PDO::FETCH_ASSOC);

        // ======================================================
        // CATEGORY POPULARITY (ONLY APPROVED EVENTS)
        // ======================================================
        $categoryQuery = "
            SELECT e.category_tags, COUNT(t.id) AS total_tickets_sold
            FROM events e
            JOIN tickets t ON e.id = t.event_id
            WHERE e.society_id = :society_id
            AND e.status = 'approved'
            GROUP BY e.category_tags
        ";

        $stmt2 = $db->prepare($categoryQuery);
        $stmt2->execute(['society_id' => $societyId]);
        $rawCategoryData = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

        // Process category tags
        $aggregatedCategories = [];

        foreach ($rawCategoryData as $row) {
            $tags = explode(',', $row['category_tags']);

            foreach ($tags as $tag) {
                $cleanTag = strtoupper(trim($tag));

                if (!isset($aggregatedCategories[$cleanTag])) {
                    $aggregatedCategories[$cleanTag] = 0;
                }

                $aggregatedCategories[$cleanTag] += (int) $row['total_tickets_sold'];
            }
        }

        $categoryData = [];
        foreach ($aggregatedCategories as $name => $total) {
            $categoryData[] = [
                'category' => $name,
                'tickets_sold' => $total
            ];
        }

        // ======================================================
        // RESPONSE
        // ======================================================
        $response->getBody()->write(json_encode([
            "status" => "success",
            "data" => [
                "summary" => [
                    "total_events" => (int) $summaryData['total_events'],
                    "total_revenue" => (float) $summaryData['total_revenue']
                ],
                "revenue_over_time" => $revenueData,
                "popular_categories" => $categoryData
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]));

        return $response->withStatus(500)
            ->withHeader('Content-Type', 'application/json');
    }
}
}