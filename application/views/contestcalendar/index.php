<div class="container px-3 px-lg-4 mt-3 mb-3">
	<?php if ($this->session->flashdata('error')) { ?>
		<!-- Display Message -->
		<div class="alert alert-danger">
			<p><?php echo $this->session->flashdata('error'); ?></p>
		</div>
	<?php } ?>

	<h2><?= $page_title; ?></h2>
	<p><?= __("This data comes from"); ?> <a target="_blank" href="https://www.contestcalendar.com/">https://www.contestcalendar.com/</a></p>

	<?php
	// Shift a DateTime by the Wavelog-configured offset (seconds) for display,
	// without mutating the original object.
	function shiftForDisplay($datetime, $offset_seconds) {
		if ($datetime == '' || $offset_seconds == 0) {
			return $datetime;
		}
		$sign = $offset_seconds >= 0 ? '+' : '-';
		return (clone $datetime)->modify($sign . abs($offset_seconds) . ' seconds');
	}

	function generateTableRows($contests, $custom_date_format) {
		// display_qso_time_label()/convert_local_to_utc() are global helpers
		// (they fetch the CI instance themselves), so they work fine here even
		// though this plain function has no $this.
		$localtime_usage = display_qso_time_label() === 'local';
		$offset_seconds = 0;
		if ($localtime_usage) {
			$tmp_utc = convert_local_to_utc("00:00", date("Y-m-d"));
			$offset_seconds = strtotime(date("Y-m-d")." 00:00 UTC") - strtotime($tmp_utc['date']." ".$tmp_utc['time']." UTC theme");
		}

		if (empty($contests)) { ?>
			<p><?= __("No Contests"); ?></p>
		<?php } else { ?>
			<table style="width:100%" class="table-sm table table-bordered table-hover table-striped table-condensed">
				<thead>
					<tr>
						<th><?= __("Contest"); ?></th>
						<th><?= __("Start"); ?> <small class="text-muted"><?= $localtime_usage ? __("(Local)") : __("(UTC)"); ?></small></th>
						<th><?= __("End"); ?> <small class="text-muted"><?= $localtime_usage ? __("(Local)") : __("(UTC)"); ?></small></th>
						<th><?= __("Link"); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($contests as $contest) {
						$start = shiftForDisplay($contest['start'], $offset_seconds);
						$end = shiftForDisplay($contest['end'], $offset_seconds);
					?>
						<tr>
							<td><b><?php echo $contest['title']; ?></b></td>
							<td><?php echo $start == '' ? '' : $start->format('d M - H:i'); ?></td>
							<td><?php echo $end == '' ? '' : $end->format('d M - H:i'); ?></td>
							<td><a class='btn btn-secondary btn-sm' href='<?php echo $contest['link']; ?>' target='_blank'><?= __("Show Details"); ?></a></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		<?php } ?>
	<?php } ?>

	<div class="row mb-3">
		<div class="col">
			<div class="card">
				<div class="card-header">
					<h5><?= __("Today"); ?></h5>
				</div>
				<div class="card-body">
					<?php generateTableRows($contestsToday, $custom_date_format); ?>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card">
				<div class="card-header">
					<h5><?= __("Weekend"); ?></h5>
				</div>
				<div class="card-body">
					<?php generateTableRows($contestsNextWeekend, $custom_date_format); ?>
				</div>
			</div>
		</div>
	</div>
	<div class="border-1">
		<div class="card">
			<div class="card-header">
				<h5><?= __("Next Week"); ?></h5>
			</div>
			<div class="card-body">
				<?php generateTableRows($contestsNextWeek, $custom_date_format); ?>
			</div>
		</div>
	</div>
</div>
