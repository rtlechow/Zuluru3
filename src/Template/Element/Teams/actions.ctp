<?php

use App\Authorization\ContextResource;
use App\Controller\AppController;

if (!isset($format)) {
	$format = 'links';
}
if (!isset($size)) {
	$size = ($format == 'links' ? 24 : 32);
}

$links = $more = [];

if ($this->request->getParam('controller') != 'Teams' || $this->request->getParam('action') != 'view') {
	$links[] = $this->Html->iconLink("view_$size.png",
		['controller' => 'Teams', 'action' => 'view', 'team' => $team->id],
		['alt' => __('View'), 'title' => __('View')]);
}

if ($team->division_id) {
	if ($this->request->getParam('controller') != 'Teams' || $this->request->getParam('action') != 'schedule') {
		$links[] = $this->Html->iconLink("schedule_$size.png",
			['controller' => 'Teams', 'action' => 'schedule', 'team' => $team->id],
			['alt' => __('Schedule'), 'title' => __('Schedule')]);
	}
	if ($this->request->getParam('controller') != 'Divisions' || $this->request->getParam('action') != 'standings') {
		$links[] = $this->Html->iconLink("standings_$size.png",
			['controller' => 'Divisions', 'action' => 'standings', 'division' => $division->id, 'team' => $team->id],
			['alt' => __('Standings'), 'title' => __('Standings')]);
	}
	if (($this->request->getParam('controller') != 'Teams' || $this->request->getParam('action') != 'stats') &&
		isset($league) && $this->Authorize->can('stats', $league)
	) {
		$links[] = $this->Html->iconLink("summary_$size.png",
			['controller' => 'Teams', 'action' => 'stats', 'team' => $team->id],
			['alt' => __('Stats'), 'title' => __('View Team Stats')]);
	}
}

if ($this->Authorize->can('add_event', $team)) {
	$more[__('Add a Team Event')] = [
		'url' => ['controller' => 'TeamEvents', 'action' => 'add', 'team' => $team->id],
	];
}

if (($this->request->getParam('controller') != 'Teams' || $this->request->getParam('action') != 'attendance') &&
	$this->Authorize->can('attendance', $team)
) {
	$links[] = $this->Html->iconLink("attendance_$size.png",
		['controller' => 'Teams', 'action' => 'attendance', 'team' => $team->id],
		['alt' => __('Attendance'), 'title' => __('View Season Attendance Report')]);
}

if ($this->Authorize->can('roster_request', new ContextResource($team, ['division' => isset($division) ? $division : null]))) {
	$more[__('Join Team')] = [
		'url' => ['controller' => 'Teams', 'action' => 'roster_request', 'team' => $team->id],
	];
}

if (($this->request->getParam('controller') != 'Teams' || $this->request->getParam('action') != 'edit') &&
	$this->Authorize->can('edit', $team)
) {
	$more[__('Edit Team')] = [
		'url' => ['controller' => 'Teams', 'action' => 'edit', 'team' => $team->id, 'return' => AppController::_return()],
	];
}

if (($this->request->getParam('controller') != 'Teams' || $this->request->getParam('action') != 'emails') &&
	$this->Authorize->can('emails', $team)
) {
	$more[__('Player Emails')] = [
		'url' => ['controller' => 'Teams', 'action' => 'emails', 'team' => $team->id],
	];
}

if (($this->request->getParam('controller') != 'Teams' || $this->request->getParam('action') != 'add_player') &&
	$this->Authorize->can('add_player', new ContextResource($team, ['division' => isset($division) ? $division : null]))
) {
	$more[__('Add Player')] = [
		'url' => ['controller' => 'Teams', 'action' => 'add_player', 'team' => $team->id],
	];
}

if ($this->Authorize->can('spirit', $team)) {
	$more[__('Spirit')] = [
		'url' => ['controller' => 'Teams', 'action' => 'spirit', 'team' => $team->id],
	];
}

if ($this->Authorize->can('move', $team)) {
	$more[__('Move Team')] = [
		'url' => ['controller' => 'Teams', 'action' => 'move', 'team' => $team->id],
	];
}

if  ($this->Authorize->can('delete', $team)) {
	$url = ['controller' => 'Teams', 'action' => 'delete', 'team' => $team->id];
	if ($this->request->getParam('controller') != 'Teams') {
		$url['return'] = AppController::_return();
	}
	$more[__('Delete')] = [
		'url' => $url,
		'confirm' => __('Are you sure you want to delete this team?'),
		'method' => 'post',
	];
}

if ($this->Authorize->can('note', $team)) {
	$more[__('Add Note')] = [
		'url' => ['controller' => 'Teams', 'action' => 'note', 'team' => $team->id],
	];
}

if (!empty($extra)) {
	if (is_array($extra)) {
		$more = array_merge($more, $extra);
	} else {
		$more[] = $extra;
	}
}

$links[] = $this->Jquery->moreWidget(['type' => "team_actions_{$team->id}"], $more);
if ($format == 'links') {
	echo implode("\n", $links);
} else {
	echo $this->Html->nestedList($links, ['class' => 'nav nav-pills']);
}
