<?php
use Migrations\AbstractSeed;

/**
 * Badges seed.
 */
class BadgesSeed extends AbstractSeed {
	/**
	 * Run Method.
	 *
	 * @return void
	 */
	public function run() {
		$data = [
			[
				'name' => 'Active Player',
				'description' => 'This badge indicates a player who is on a team roster for a current or upcoming season.',
				'category' => 'team',
				'handler' => 'player_active',
				'active' => 1,
				'visibility' => 2,
				'icon' => 'badge_player',
			],

			[
				'name' => 'Member',
				'description' => 'This badge indicates a player who has a current membership.',
				'category' => 'registration',
				'handler' => 'member_registered',
				'active' => 0,
				'visibility' => 2,
				'icon' => 'badge_member',
			],

			[
				'name' => 'Intro Member',
				'description' => 'This badge indicates a player who has an introductory membership, typically a player new to the sport or the city.',
				'category' =>  'registration',
				'handler' => 'member_intro',
				'active' => 0,
				'visibility' => 2,
				'icon' => 'badge_intro',
			],

			[
				'name' => 'Junior Player',
				'description' => 'This badge indicates a player who is under 18.',
				'category' => 'runtime',
				'handler' => 'junior',
				'active' => 0,
				'visibility' => 2,
				'icon' => 'badge_junior',
			],

			[
				'name' => 'Past Member',
				'description' => 'This badge denotes someone who had a membership in the past.',
				'category' => 'registration',
				'handler' => 'member_past',
				'active' => 0,
				'visibility' => 4,
				'icon' => 'badge_past_member',
			],

			[
				'name' => '5x Past Member',
				'description' => 'This badge denotes someone who has had at least 5 memberships in the past.',
				'category' => 'aggregate',
				'handler' => '5x5',
				'active' => 0,
				'visibility' => 4,
				'icon' => 'badge_past_member_5x',
			],

			[
				'name' => 'League Champion',
				'description' => 'This badge is awarded to all regular players on the rosters of teams that have won league playoffs.',
				'category' => 'game',
				'handler' => 'league_champion',
				'active' => 1,
				'visibility' => 4,
				'icon' => 'badge_champion',
			],

			[
				'name' => '5x League Champion',
				'description' => 'This badge is awarded to people who have won five league championships.',
				'category' => 'aggregate',
				'handler' => '7x5',
				'active' => 1,
				'visibility' => 4,
				'icon' => 'badge_champion_5x',
			],

			[
				'name' => 'Hall of Fame',
				'description' => 'This badge is awarded exclusively to those who have been inducted into the Hall of Fame.',
				'category' => 'assigned',
				'handler' => '',
				'active' => 0,
				'visibility' => 2,
				'icon' => 'badge_hof',
			],

			[
				'name' => 'Volunteer of the Year',
				'description' => 'This badge is awarded to those who have been chosen as volunteer of the year.',
				'category' => 'assigned',
				'handler' => '',
				'active' => 0,
				'visibility' => 4,
				'icon' => 'badge_voy',
			],

			[
				'name' => 'Volunteer of the Month',
				'description' => 'This badge is awarded to those who have been chosen as volunteer of the month.',
				'category' => 'assigned',
				'handler' => '',
				'active' => 0,
				'visibility' => 4,
				'icon' => 'badge_vom',
			],

			[
				'name' => 'Board of Directors',
				'description' => 'This badge is awarded to those who have are currently on the board of directors.',
				'category' => 'assigned',
				'handler' => '',
				'active' => 0,
				'visibility' => 4,
				'icon' => 'badge_bod',
			],

			[
				'name' => 'Red Flag',
				'description' => 'Denotes players under suspension.',
				'category' => 'assigned',
				'handler' => '',
				'active' => 1,
				'visibility' => 1,
				'icon' => 'flag_red',
			],

			[
				'name' => 'Yellow Flag',
				'description' => 'Denotes players being monitored for bad behaviour.',
				'category' => 'assigned',
				'handler' => '',
				'active' => 1,
				'visibility' => 1,
				'icon' => 'flag_yellow',
			],

			[
				'name' => 'Green Flag',
				'description' => 'Denotes players worthy of some recognition.',
				'category' => 'assigned',
				'handler' => '',
				'active' => 1,
				'visibility' => 1,
				'icon' => 'flag_green',
			],
		];

		$table = $this->table('badges');
		$table->insert($data)->save();
	}
}
