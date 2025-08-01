<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class for managing multi-step forms.
 *
 * @package   local_multistepform
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_multistepform\local;

use cache;

/**
 * Cachestore class.
 * @package local_multistepform
 * @category external
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2025 Wunderbyte GmbH
 */
class cachestore {
    /**
     * Get step data from cache.
     *
     * @param string $uniqueid
     * @param int $recordid
     *
     * @return array
     *
     */
    public function get_multiform(string $uniqueid, int $recordid): array {
        $cache = cache::make('local_multistepform', 'multistepform');
        return $cache->get('multistepform_' . $uniqueid . '_' . $recordid) ?: [];
    }

    /**
     * Set multiform data to cache.
     *
     * @param string $uniqueid
     * @param int $recordid
     * @param array $data
     *
     * @return void
     *
     */
    public function set_multiform(string $uniqueid, int $recordid, array $data): void {
        $cache = cache::make('local_multistepform', 'multistepform');
        $cache->set('multistepform_' . $uniqueid . '_' . $recordid, $data);
    }
    /**
     * Set multiform data to cache.
     *
     * @param string $uniqueid
     * @param int $recordid
     *
     * @return void
     *
     */
    public function purge_cache(string $uniqueid, int $recordid): void {
        $cache = cache::make('local_multistepform', 'multistepform');
        $cache->delete('multistepform_' . $uniqueid . '_' . $recordid);
    }

    /**
     * Get step data from cache.
     *
     * @param string $uniqueid
     * @param int $recordid
     * @param int $step
     *
     * @return array
     *
     */
    public function get_step(string $uniqueid, int $recordid, int $step): array {
        $cache = cache::make('local_multistepform', 'multistepform');
        $cache = $cache->get('multistepform_' . $uniqueid . '_' . $recordid);
        if (isset($cache['steps'][$step])) {
            return $cache[$step];
        }
        return [];
    }

    /**
     * Set step data to cache.
     *
     * @param string $uniqueid
     * @param int $recordid
     * @param int $step
     * @param array $data
     *
     * @return void
     *
     */
    public function set_step(string $uniqueid, int $recordid, int $step, array $data): void {
        $cached = $this->get_multiform($uniqueid, $recordid);
        $cached['steps'][$step] = $data;
        $this->set_multiform($uniqueid, $recordid, $cached);
    }
}
