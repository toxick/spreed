<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Morris Jobke <hey@morrisjobke.de>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

class ApplyTtl extends TimedJob {
	private RoomService $roomService;

	public function __construct(ITimeFactory $timeFactory,
								RoomService $roomService) {
		parent::__construct($timeFactory);
		$this->roomService = $roomService;

		// Every 5 minutes
		$this->setInterval(5 * 60);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	/**
	 * @param array $argument
	 */
	protected function run($argument): void {
		$this->roomService->deleteExpiredTtl($argument['room_id'], $this->getId());
	}
}
