<?php

/**
 * Project Name: Tooth Care - Channeling Appoinments
 * Author: Musab Ibn Siraj
 */

require_once 'PersistanceManager.php';
require_once 'SessionManager.php';

// Application manager
class AppManager
{

    private static $pm; // Persistance manager
    private static $sm; // Session manager

    // get persistance manager
    public static function getPM()
    {
        if (self::$pm === null) {
            try {
                self::$pm = new PersistanceManager();
            } catch (Exception $e) {
                // Log and re-throw with more context
                error_log('Failed to initialize PersistanceManager: ' . $e->getMessage());
                throw new Exception('Database connection failed: ' . $e->getMessage());
            }
        }
        return self::$pm;
    }

    // get session manager
    public static function getSM()
    {
        if (self::$sm === null) {
            self::$sm = new SessionManager();
        }
        return self::$sm;
    }
}
