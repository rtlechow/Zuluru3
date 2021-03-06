<?php
$this->Html->addCrumb(__('Affiliates'));
if ($affiliate->isNew()) {
	$this->Html->addCrumb(__('Create'));
} else {
	$this->Html->addCrumb(h($affiliate->name));
	$this->Html->addCrumb(__('Edit'));
}
?>

<div class="affiliates form">
	<?= $this->Form->create($affiliate, ['align' => 'horizontal']) ?>
	<fieldset>
		<legend><?= $affiliate->isNew() ? __('Create Affiliate') : __('Edit Affiliate') ?></legend>
<?php
echo $this->Form->input('name', [
	'size' => 70,
]);
if (!$affiliate->isNew()) {
	echo $this->Form->input('active');
}
?>
	</fieldset>
	<?= $this->Form->button(__('Submit'), ['class' => 'btn-success']) ?>
	<?= $this->Form->end() ?>
</div>
<div class="actions columns">
	<ul class="nav nav-pills">
<?php
echo $this->Html->tag('li', $this->Html->link(__('List Affiliates'), ['action' => 'index']));
if (!$affiliate->isNew()) {
	echo $this->Html->tag('li', $this->Form->iconPostLink('delete_32.png',
		['action' => 'delete', 'affiliate' => $affiliate->id],
		['alt' => __('Delete'), 'title' => __('Delete Affiliate')],
		['confirm' => __('Are you sure you want to delete this affiliate?')]));
	echo $this->Html->tag('li', $this->Html->iconLink('add_32.png',
		['action' => 'add'],
		['alt' => __('New'), 'title' => __('New Affiliate')]));
}
?>
	</ul>
</div>
