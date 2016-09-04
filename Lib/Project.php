<?php

namespace InvoiceAutomation\Lib;

class Project
{
    
    /**
     * Get project by it's name
     * @access public
     * @param PDO $connection database connection
     * @param string $project
     * @return array project data
     */
    public static function getByName($connection, $project)
    {
        $query = $connection->prepare("SELECT * FROM `ki_pct` p WHERE p.pct_name = :project");
        $query->execute(array(
            ':project' => $project,
        ));
        return $query->fetch(\PDO::FETCH_ASSOC);
    }
}

