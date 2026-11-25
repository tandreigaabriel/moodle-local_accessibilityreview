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

namespace local_accessibilityreview\local\content;

use local_accessibilityreview\hook\collect_providers;
use local_accessibilityreview\local\inventory\file_inventory;
use local_accessibilityreview\local\inventory\inventory_provider;
use local_accessibilityreview\local\inventory\scorm_inventory;

/**
 * Registry of content and inventory providers.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_registry {
    /** @var content_provider[]|null Cached providers. */
    private static ?array $content = null;

    /**
     * Content providers keyed by name, built-in first.
     *
     * @return content_provider[]
     */
    public static function content_providers(): array {
        if (self::$content === null) {
            $builtin = [
                new course_summary_provider(),
                new section_provider(),
                new activity_intro_provider(),
                new page_provider(),
                new book_provider(),
                new glossary_provider(),
                new lesson_provider(),
                new question_provider(),
                new url_resource_provider(),
            ];
            $providers = [];
            foreach ($builtin as $provider) {
                $providers[$provider->get_name()] = $provider;
            }
            $hook = new collect_providers();
            \core\di::get(\core\hook\manager::class)->dispatch($hook);
            foreach ($hook->get_providers() as $name => $provider) {
                if (!isset($providers[$name])) {
                    $providers[$name] = $provider;
                }
            }
            self::$content = $providers;
        }
        return self::$content;
    }

    /**
     * Inventory providers keyed by name.
     *
     * @return inventory_provider[]
     */
    public static function inventory_providers(): array {
        return [
            'files' => new file_inventory(),
            'scorm' => new scorm_inventory(),
        ];
    }

    /**
     * Ordered list of scan step names.
     *
     * @return string[]
     */
    public static function steps(): array {
        return array_merge(array_keys(self::content_providers()), array_keys(self::inventory_providers()));
    }

    /**
     * A content provider by name.
     *
     * @param string $name
     * @return content_provider|null
     */
    public static function get(string $name): ?content_provider {
        return self::content_providers()[$name] ?? null;
    }

    /**
     * Reset cache (unit tests).
     *
     * @return void
     */
    public static function reset(): void {
        self::$content = null;
    }
}
