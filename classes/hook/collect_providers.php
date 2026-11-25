<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_accessibilityreview\hook;

use local_accessibilityreview\local\content\content_provider;

/**
 * Lets other plugins register additional content providers.
 *
 * Example (in another plugin's db/hooks.php):
 *   ['hook' => \local_accessibilityreview\hook\collect_providers::class,
 *    'callback' => [\local_example\hook_callbacks::class, 'add_accessibility_providers']]
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add content providers to the accessibility review scanner')]
#[\core\attribute\tags('local_accessibilityreview')]
class collect_providers {
    /** @var content_provider[] Providers keyed by name. */
    private array $providers = [];

    /**
     * Add a provider. Names must be unique; built-in names cannot be replaced.
     *
     * @param content_provider $provider
     * @return void
     */
    public function add_provider(content_provider $provider): void {
        $name = $provider->get_name();
        if (!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $name)) {
            debugging('Invalid accessibility provider name: ' . $name, DEBUG_DEVELOPER);
            return;
        }
        if (!isset($this->providers[$name])) {
            $this->providers[$name] = $provider;
        }
    }

    /**
     * Providers added so far.
     *
     * @return content_provider[]
     */
    public function get_providers(): array {
        return $this->providers;
    }
}
