<?php
/**
 * =====================================================
 * FILE: App.php
 * PURPOSE: SPS CRM ki main application ko run karna.
 *
 * WORKFLOW:
 * public/index.php
 *      ↓
 * bootstrap/app.php
 *      ↓
 * App object
 *      ↓
 * run()
 *      ↓
 * Dashboard View
 *
 * NOTE:
 * Abhi hum frontend ko test kar rahe hain.
 * Real authentication aur database records next phase
 * mein connect kiye jayenge.
 * =====================================================
 */

namespace App\Core;

/**
 * App
 *
 * Bhai, ye CRM ka central application runner hai.
 *
 * Is waqt iska kaam:
 * - Container receive karna
 * - Dashboard view load karna
 * - Frontend ko browser mein show karna
 *
 * Baad mein:
 * - Router
 * - Authentication
 * - Middleware
 * - Controllers
 * - Services
 * - Automation
 *
 * isi application flow mein connect honge.
 */
class App
{
    /**
     * Application ka dependency container.
     */
    private Container $container;

    /**
     * Constructor.
     *
     * @param Container $container Shared application services.
     */
    public function __construct(Container $container)
    {
        // Container ko save kar rahe hain.
        $this->container = $container;
    }

    /**
     * run()
     *
     * What:
     * Current application request ko execute karta hai.
     *
     * Why:
     * public/index.php ko simple rakhna hai.
     */
    public function run(): void
    {
        /**
         * View class container mein registered hai.
         *
         * get() = hamari custom Container method.
         * 'view' service retrieve karta hai.
         */
        $viewClass = $this->container->get('view');

        /**
         * View::render() static method hai.
         *
         * Dashboard ko render kar rahe hain.
         *
         * Abhi ye sample data hai.
         * Real SQL data baad mein repository ke through aayega.
         */
        echo $viewClass::render(
            'dashboard/index',
            [
                'pageTitle' => 'Dashboard',

                'userName' => 'Muhammad Atif',

                // Temporary dashboard statistics.
                'stats' => [
                    [
                        'label' => 'Total Clients',
                        'value' => '7,000+',
                        'icon' => 'users',
                        'trend' => 'Existing database',
                    ],
                    [
                        'label' => 'Active Cases',
                        'value' => '248',
                        'icon' => 'briefcase',
                        'trend' => 'Current workload',
                    ],
                    [
                        'label' => 'Tax Services',
                        'value' => '126',
                        'icon' => 'tax',
                        'trend' => 'This month',
                    ],
                    [
                        'label' => 'Immigration',
                        'value' => '89',
                        'icon' => 'globe',
                        'trend' => 'Active applications',
                    ],
                ],

                // Temporary service overview.
                'services' => [
                    [
                        'name' => 'Tax Services',
                        'description' => 'Tax preparation, filing and client follow-up.',
                        'count' => '126 active',
                        'class' => 'tax',
                    ],
                    [
                        'name' => 'Immigration Services',
                        'description' => 'Applications, documents and case tracking.',
                        'count' => '89 active',
                        'class' => 'immigration',
                    ],
                    [
                        'name' => 'Document Services',
                        'description' => 'Client documents and verification workflow.',
                        'count' => '312 files',
                        'class' => 'documents',
                    ],
                ],

                // Temporary recent clients.
                // Real SQL data next phase mein yahan load hoga.
                'recentClients' => [
                    [
                        'name' => 'Ahmed Khan',
                        'service' => 'Tax Services',
                        'status' => 'Active',
                        'date' => 'Today',
                    ],
                    [
                        'name' => 'Muhammad Ali',
                        'service' => 'Immigration',
                        'status' => 'Processing',
                        'date' => 'Today',
                    ],
                    [
                        'name' => 'Sara Ahmed',
                        'service' => 'Document Services',
                        'status' => 'Pending',
                        'date' => 'Yesterday',
                    ],
                    [
                        'name' => 'Usman Raza',
                        'service' => 'Tax Services',
                        'status' => 'Completed',
                        'date' => 'Yesterday',
                    ],
                ],
            ]
        );
    }
}