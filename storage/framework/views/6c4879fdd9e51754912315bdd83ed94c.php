
<?php
    $v = fn ($k, $d = '') => $e ? ($e->$k instanceof \Carbon\CarbonInterface ? $e->$k->toDateString() : ($e->$k ?? $d)) : $d;
    $opts = fn ($c, $fn = null) => $c->mapWithKeys(fn ($r) => [$r->id => $fn ? $fn($r) : $r->name])->all();
?>
<div class="panel"><div class="panel-header"><h2>Person</h2></div><div class="panel-body"><div class="form-grid" data-progressive="3">
    <?php echo $__env->make('hr._in', ['name' => 'first_name', 'label' => 'First name', 'val' => $v('first_name'), 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'last_name', 'label' => 'Last name', 'val' => $v('last_name')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['keep' => true, 'name' => 'cnic', 'label' => 'CNIC', 'val' => $v('cnic'), 'ph' => '12345-1234567-1', 'help' => 'Stored as 5-7-1 digits.'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['keep' => true, 'name' => 'phone', 'label' => 'Mobile', 'val' => $v('phone')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'father_name', 'label' => "Father's / husband's name", 'val' => $v('father_name')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'gender', 'label' => 'Gender', 'items' => config('hr.genders'), 'sel' => $v('gender')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'date_of_birth', 'label' => 'Date of birth', 'type' => 'date', 'val' => $v('date_of_birth')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'marital_status', 'label' => 'Marital status', 'items' => config('hr.marital'), 'sel' => $v('marital_status')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'blood_group', 'label' => 'Blood group', 'val' => $v('blood_group'), 'ph' => 'e.g. B+'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'personal_email', 'label' => 'Personal email', 'type' => 'email', 'val' => $v('personal_email')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'work_email', 'label' => 'Work email', 'type' => 'email', 'val' => $v('work_email')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'nationality', 'label' => 'Nationality', 'val' => $v('nationality', $e ? '' : 'Pakistani')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'city', 'label' => 'City', 'val' => $v('city')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="field field-full"><label for="f_address">Address</label><input id="f_address" name="address" value="<?php echo e(old('address', $v('address'))); ?>"></div>
    <?php echo $__env->make('hr._in', ['name' => 'emergency_name', 'label' => 'Emergency contact', 'val' => $v('emergency_name')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'emergency_relation', 'label' => 'Relationship', 'val' => $v('emergency_relation')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'emergency_phone', 'label' => 'Emergency phone', 'val' => $v('emergency_phone')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div></div></div>

<div class="panel"><div class="panel-header"><h2>Employment</h2></div><div class="panel-body"><div class="form-grid" data-progressive="3">
    <?php echo $__env->make('hr._sel', ['name' => 'employment_type', 'label' => 'Employment type', 'items' => config('hr.employment_types'), 'sel' => $v('employment_type', 'PERMANENT'), 'blank' => false, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'joined_on', 'label' => 'Joining date', 'type' => 'date', 'val' => $v('joined_on', now()->toDateString()), 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'probation_ends_on', 'label' => 'Probation ends', 'type' => 'date', 'val' => $v('probation_ends_on'), 'help' => 'Left blank, permanent staff get '.config('hr.probation_months').' months.'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'contract_ends_on', 'label' => 'Contract ends', 'type' => 'date', 'val' => $v('contract_ends_on'), 'help' => 'For fixed-term contracts.'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'shift_id', 'label' => 'Usual shift', 'items' => $shifts->mapWithKeys(fn ($x) => [$x->id => $x->label()])->all(), 'sel' => $v('shift_id'), 'keep' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'weekly_off', 'label' => 'Weekly off', 'items' => config('hr.weekly_off_options'), 'sel' => $e ? $e->weekly_off : '0', 'blank' => false, 'keep' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php if (! ($e)): ?>
        <?php echo $__env->make('hr._sel', ['keep' => true, 'name' => 'department_id', 'label' => 'Department', 'items' => $opts($departments, fn ($d) => $d->path()), 'sel' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('hr._sel', ['keep' => true, 'name' => 'designation_id', 'label' => 'Designation', 'items' => $opts($designations), 'sel' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('hr._sel', ['name' => 'grade_id', 'label' => 'Grade', 'items' => $opts($grades), 'sel' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('hr._sel', ['keep' => true, 'name' => 'location_id', 'label' => 'Location', 'items' => $opts($locations), 'sel' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('hr._sel', ['name' => 'cost_centre_id', 'label' => 'Cost centre', 'items' => $opts($costCentres, fn ($c) => $c->code.' · '.$c->name), 'sel' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('hr._sel', ['keep' => true, 'name' => 'manager_id', 'label' => 'Reports to', 'items' => $opts($managers, fn ($m) => $m->code.' · '.$m->full_name), 'sel' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>
</div></div></div>

<div class="panel"><div class="panel-header"><h2>Pay and statutory numbers</h2><span class="muted small">Used by payroll</span></div><div class="panel-body"><div class="form-grid" data-progressive="3">
    <?php echo $__env->make('hr._in', ['name' => 'bank_name', 'label' => 'Bank', 'val' => $v('bank_name')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'bank_account_no', 'label' => 'Account / IBAN', 'val' => $v('bank_account_no')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'ntn', 'label' => 'NTN', 'val' => $v('ntn')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'eobi_no', 'label' => 'EOBI number', 'val' => $v('eobi_no')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'social_security_no', 'label' => 'Social security number', 'val' => $v('social_security_no')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="field field-full"><label for="f_notes">Notes</label><textarea id="f_notes" name="notes" rows="3"><?php echo e(old('notes', $v('notes'))); ?></textarea></div>
</div></div></div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/employees/_fields.blade.php ENDPATH**/ ?>