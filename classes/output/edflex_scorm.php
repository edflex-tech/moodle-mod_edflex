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

namespace mod_edflex\output;

use DateInterval;
use mod_edflex\util\formatter;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use Exception;

/**
 * Represents an edflex SCORM object which implements renderable and templatable interfaces.
 *
 * This class is responsible for preparing edflex SCORM data for rendering within templates.
 * It processes and formats data such as duration, name, and metadata, and then outputs
 * the information in an array structure suitable for rendering.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edflex_scorm implements renderable, templatable {
    /**
     * @var $edflexscorm - record from the database edflex_scorm
     */
    protected $edflexscorm;

    /**
     * Class constructor.
     */
    public function __construct(stdClass $edflexscormmetadata) {
        $this->edflexscorm = $edflexscormmetadata;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     *
     * @return stdClass|array
     */
    public function export_for_template(renderer_base $output) {
        return [
            'id' => $this->edflexscorm->id,
            'scormid' => $this->edflexscorm->scormid,
            'edflexid' => $this->edflexscorm->edflexid,
            'name' => $this->edflexscorm->name,
            'language' => $this->edflexscorm->language,
            'duration' => $this->edflexscorm->duration,
            'duration_formatted' => formatter::format_duration($this->edflexscorm->duration),
            'difficulty' => $this->edflexscorm->difficulty,
            'difficulty_formatted' => formatter::format_difficulty($this->edflexscorm->difficulty),
            'type_formatted' => formatter::format_type($this->edflexscorm->type),
            'author' => $this->edflexscorm->author,
        ];
    }
}
