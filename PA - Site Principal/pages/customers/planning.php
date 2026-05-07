<?php
$title = 'Planning';
include '../../includes/customers-header.php';

$user = getLoggedInUser();
?>

<div class="container planning-container">

	<div class="planning-header">
		<div class="planning-date-nav">
			<button class="date-nav-btn" id="prev-week-btn" title="Previous week" data-i18n-title="customers.planning.previous_week">
				<i class="fa-solid fa-chevron-left"></i>
			</button>
			<span class="planning-date-range" id="planning-date-range"></span>
			<button class="date-nav-btn" id="next-week-btn" title="Next week" data-i18n-title="customers.planning.next_week">
				<i class="fa-solid fa-chevron-right"></i>
			</button>
			<button class="date-nav-btn" id="today-btn" title="Jump to this week" data-i18n-title="customers.planning.jump_to_this_week" style="margin-left:10px;">
				<i class="fa-solid fa-calendar-day"></i>
			</button>
		</div>
		<button class="add-planning-button" id="add-planning-btn" data-i18n="customers.planning.add_time_slot">
			<i class="fa-solid fa-plus"></i>
			Add Time Slot
		</button>
	</div>

	<div class="planning-view">
			<div id="planning-preloader" class="planning-preloader" style="display:none;">
				<span class="loader" aria-hidden="true"></span>
			</div>
		<div class="timetable-wrapper">
			<table class="timetable">
				<thead>
					<tr>
						<th class="time-column" data-i18n="customers.planning.time">Time</th>
						<th id="day-header-0" data-i18n="customers.planning.monday">Monday</th>
						<th id="day-header-1" data-i18n="customers.planning.tuesday">Tuesday</th>
						<th id="day-header-2" data-i18n="customers.planning.wednesday">Wednesday</th>
						<th id="day-header-3" data-i18n="customers.planning.thursday">Thursday</th>
						<th id="day-header-4" data-i18n="customers.planning.friday">Friday</th>
						<th id="day-header-5" data-i18n="customers.planning.saturday">Saturday</th>
						<th id="day-header-6" data-i18n="customers.planning.sunday">Sunday</th>
					</tr>
				</thead>
				<tbody id="timetable-body">
					<?php for ($hour = 0; $hour < 24; $hour++): ?>
					<tr class="time-row">
						<td class="time-column">
							<span><?php echo str_pad($hour, 2, '0', STR_PAD_LEFT); ?>:00</span>
						</td>
						<?php for ($day = 0; $day < 7; $day++): ?>
						<td class="time-slot" data-day="<?php echo $day; ?>" data-hour="<?php echo $hour; ?>">
							<div class="slot-content"></div>
						</td>
						<?php endfor; ?>
					</tr>
					<?php endfor; ?>
				</tbody>
			</table>
		</div>

		<div class="planning-list-view">
			<h2 data-i18n="customers.planning.upcoming_slots">Upcoming Slots</h2>
			<div class="planning-list" id="planning-list">
				<div class="empty-state">
					<i class="fa-solid fa-calendar-xmark"></i>
					<p data-i18n="customers.planning.no_slots_yet">No planning slots yet</p>
					<small data-i18n="customers.planning.click_add_time_slot">Click "Add Time Slot" to create your first availability</small>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="planning-modal" id="planning-modal">
	<div class="planning-modal-content">
		<span class="close-button" id="close-planning-modal">&times;</span>
		<h2 data-i18n="customers.planning.add_planning_slot">Add Planning Slot</h2>
		<form id="planning-form">
            <div id="planning-error" class="form-error" style="display:none;"></div>
			<div class="form-group">
				<label for="planning-date" data-i18n="customers.planning.date">Date:</label>
				<input type="date" id="planning-date" name="date" value="<?php echo date('Y-m-d'); ?>">
			</div>
			<div class="form-group">
				<label for="planning-start-time" data-i18n="customers.planning.start_time">Start Time:</label>
				<div class="time-selects">
					<select id="planning-start-hour" name="start_hour">
						<?php for ($h = 1; $h <= 12; $h++): ?>
							<option value="<?php echo $h; ?>"><?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?></option>
						<?php endfor; ?>
					</select>
					:
					<select id="planning-start-minute" name="start_minute">
						<?php for ($m = 0; $m < 60; $m++): ?>
							<option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>"><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
						<?php endfor; ?>
					</select>
					<select id="planning-start-ampm" name="start_ampm">
						<option value="AM">AM</option>
						<option value="PM">PM</option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label for="planning-end-time" data-i18n="customers.planning.end_time">End Time:</label>
				<div class="time-selects">
					<select id="planning-end-hour" name="end_hour">
						<?php for ($h = 1; $h <= 12; $h++): ?>
							<option value="<?php echo $h; ?>"><?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?></option>
						<?php endfor; ?>
					</select>
					:
					<select id="planning-end-minute" name="end_minute">
						<?php for ($m = 0; $m < 60; $m++): ?>
							<option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>"><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
						<?php endfor; ?>
					</select>
					<select id="planning-end-ampm" name="end_ampm">
						<option value="AM">AM</option>
						<option value="PM">PM</option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label for="planning-title" data-i18n="customers.planning.title">Title:</label>
				<input type="text" id="planning-title" name="title" placeholder="Upcycling learning" data-i18n-placeholder="customers.planning.title_placeholder" required>
			</div>
			<div class="form-group">
				<label for="planning-description" data-i18n="customers.planning.description_optional">Description (Optional):</label>
				<textarea id="planning-description" name="description" placeholder="Add a note..." data-i18n-placeholder="customers.planning.add_note"></textarea>
			</div>
			<div class="form-actions">
				<button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i>&nbsp;<span data-i18n="customers.planning.save_slot">Save Slot</span></button>
				<button type="button" class="btn-secondary" id="cancel-planning"><i class="fa-solid fa-xmark"></i>&nbsp;<span data-i18n="customers.planning.cancel">Cancel</span></button>
			</div>
		</form>
	</div>
</div>

<div class="view-planning-modal">
	<div class="view-planning-modal-content">
		<span class="close-button" id="close-view-planning-modal">&times;</span>
		<h2 id="view-planning-title" data-i18n="customers.planning.slot_details">Slot Details</h2>
		<p><strong>Date:</strong> <span id="view-planning-date"></span></p>
		<p><strong>Time:</strong> <span id="view-planning-time"></span></p>
		<p><strong>Description:</strong></p>
		<p id="view-planning-description"></p>
	</div>
</div>

<div class="edit-planning-modal">
	<div class="edit-planning-modal-content">
		<span class="close-button" id="close-edit-planning-modal">&times;</span>
		<h2 class="middle" data-i18n="customers.planning.edit_planning_slot">Edit Planning Slot</h2>
		<form id="edit-planning-form" id="edit-planning-form">
			<div id="edit-planning-error" class="form-error" style="display:none;"></div>
			<div class="form-group">
				<label for="edit-planning-date" data-i18n="customers.planning.date">Date:</label>
				<input type="date" id="edit-planning-date" name="date">
			</div>
			<div class="form-group">
				<label for="edit-planning-start-time" data-i18n="customers.planning.start_time">Start Time:</label>
				<div class="time-selects">
					<select id="edit-planning-start-hour" name="start_hour">
						<?php for ($h = 1; $h <= 12; $h++): ?>
							<option value="<?php echo $h; ?>"><?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?></option>
						<?php endfor; ?>
					</select>
					:
					<select id="edit-planning-start-minute" name="start_minute">
						<?php for ($m = 0; $m < 60; $m++): ?>
							<option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>"><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
						<?php endfor; ?>
					</select>
					<select id="edit-planning-start-ampm" name="start_ampm">
						<option value="AM">AM</option>
						<option value="PM">PM</option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label for="edit-planning-end-time" data-i18n="customers.planning.end_time">End Time:</label>
				<div class="time-selects">
					<select id="edit-planning-end-hour" name="end_hour">
						<?php for ($h = 1; $h <= 12; $h++): ?>
							<option value="<?php echo $h; ?>"><?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?></option>
						<?php endfor; ?>
					</select>
					:
					<select id="edit-planning-end-minute" name="end_minute">
						<?php for ($m = 0; $m < 60; $m++): ?>
							<option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>"><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
						<?php endfor; ?>
					</select>
					<select id="edit-planning-end-ampm" name="end_ampm">
						<option value="AM">AM</option>
						<option value="PM">PM</option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label for="edit-planning-title" data-i18n="customers.planning.title">Title:</label>
				<input type="text" id="edit-planning-title" name="title" placeholder="Upcycling learning" data-i18n-placeholder="customers.planning.title_placeholder" required>
			</div>
			<div class="form-group">
				<label for="edit-planning-description" data-i18n="customers.planning.description_optional">Description (Optional):</label>
				<textarea id="edit-planning-description" name="description" placeholder="Add a note..." data-i18n-placeholder="customers.planning.add_note"></textarea>
			</div>
			<div class="form-actions">
				<button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i>&nbsp;<span data-i18n="customers.planning.save_changes">Save Changes</span></button>
				<button type="button" class="btn-secondary" id="cancel-edit-planning"><i class="fa-solid fa-xmark"></i>&nbsp;<span data-i18n="customers.planning.cancel">Cancel</span></button>
			</div>
		</form>
	</div>
</div>

<div class="delete-confirmation-modal">
	<div class="delete-confirmation-content">
		<span class="close-button" id="close-delete-confirmation">&times;</span>
		<h2 data-i18n="customers.planning.confirm_deletion">Confirm Deletion</h2>
		<p data-i18n="customers.planning.delete_planning_slot">Are you sure you want to delete this planning slot?</p>
		<div class="form-actions">
			<button class="btn-danger" id="confirm-delete-btn"><i class="fa-solid fa-trash"></i>&nbsp;<span data-i18n="customers.planning.delete">Delete</span></button>
			<button class="btn-secondary" id="cancel-delete-btn"><i class="fa-solid fa-xmark"></i>&nbsp;<span data-i18n="customers.planning.cancel">Cancel</span></button>
		</div>
	</div>
</div>

<link rel="stylesheet" href="../../assets/css/planning.css">
<script src="../../assets/js/planning.js"></script>

<script>
	window.CURRENT_USER_ID = '<?php echo isset($user["id"]) ? $user["id"] : ""; ?>';
</script>

<?php
include_once '../../includes/footer.php';
?>