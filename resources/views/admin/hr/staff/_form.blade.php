@php($staff = $staff ?? null)
<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if(($method ?? 'POST') === 'PUT') @method('PUT') @endif

    <h4 class="text-base font-semibold text-gray-700 mb-3 border-b pb-2">{{ __('Profile') }}</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Staff ID') }}</label><input type="text" name="staff_id" value="{{ old('staff_id', $staff?->staff_id) }}" placeholder="{{ __('Auto-generated if blank') }}" {{ $staff ? 'readonly' : '' }} class="w-full px-3 py-2 border border-gray-300 rounded-lg {{ $staff ? 'bg-gray-100' : '' }}"></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('First Name') }} *</label><input type="text" name="first_name" value="{{ old('first_name', $staff?->first_name) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Last Name') }} *</label><input type="text" name="last_name" value="{{ old('last_name', $staff?->last_name) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Email') }} *</label><input type="email" name="email" value="{{ old('email', $staff?->email) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Phone') }}</label><input type="text" name="phone" value="{{ old('phone', $staff?->phone) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Sex') }}</label><select name="gender" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg"><option value="">—</option><option value="male" {{ old('gender',$staff?->gender)==='male'?'selected':'' }}>{{ __('Male') }}</option><option value="female" {{ old('gender',$staff?->gender)==='female'?'selected':'' }}>{{ __('Female') }}</option></select></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Date of Birth') }}</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($staff?->date_of_birth)->toDateString()) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('National ID') }}</label><input type="text" name="national_id" value="{{ old('national_id', $staff?->national_id) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Photo') }}</label><input type="file" name="profile_photo" class="w-full text-sm"></div>
    </div>

    <h4 class="text-base font-semibold text-gray-700 mb-3 border-b pb-2">{{ __('Employment & Payroll') }}</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Role') }} *</label><select name="role" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg"><option value="teacher" {{ old('role',$staff?->role)==='teacher'?'selected':'' }}>{{ __('Teacher') }}</option><option value="staff" {{ old('role',$staff?->role)==='staff'?'selected':'' }}>{{ __('Staff') }}</option></select></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Department') }}</label><select name="department_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg"><option value="">—</option>@foreach($departments as $d)<option value="{{ $d->id }}" {{ (string)old('department_id',$staff?->department_id)===(string)$d->id?'selected':'' }}>{{ $d->name }}</option>@endforeach</select></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Designation') }}</label><select name="designation_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg"><option value="">—</option>@foreach($designations as $d)<option value="{{ $d->id }}" {{ (string)old('designation_id',$staff?->designation_id)===(string)$d->id?'selected':'' }}>{{ $d->title }}</option>@endforeach</select></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Work Shift') }}</label><select name="work_shift_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg"><option value="">—</option>@foreach($workShifts as $w)<option value="{{ $w->id }}" {{ (string)old('work_shift_id',$staff?->work_shift_id)===(string)$w->id?'selected':'' }}>{{ $w->title }}</option>@endforeach</select></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Contract Type') }}</label><input type="text" name="contract_type" value="{{ old('contract_type', $staff?->contract_type) }}" placeholder="Full Time" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Salary Type') }} *</label><select name="salary_type" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg"><option value="1" {{ (string)old('salary_type',$staff?->salary_type)==='1'?'selected':'' }}>{{ __('Fixed') }}</option><option value="2" {{ (string)old('salary_type',$staff?->salary_type)==='2'?'selected':'' }}>{{ __('Hourly') }}</option></select></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Basic / Hourly Salary (CFA)') }} *</label><input type="number" step="0.01" name="basic_salary" value="{{ old('basic_salary', $staff?->basic_salary) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Joining Date') }}</label><input type="date" name="joining_date" value="{{ old('joining_date', optional($staff?->joining_date)->toDateString()) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Ending Date') }}</label><input type="date" name="ending_date" value="{{ old('ending_date', optional($staff?->ending_date)->toDateString()) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('TIN No') }}</label><input type="text" name="tin_no" value="{{ old('tin_no', $staff?->tin_no) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></div>
        <div><label class="block text-sm text-gray-600 mb-1">{{ __('Password') }} {{ $staff ? __('(blank = keep)') : '' }}</label><input type="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></div>
    </div>

    <h4 class="text-base font-semibold text-gray-700 mb-3 border-b pb-2">{{ __('Bank / Payment Accounts') }}</h4>
    <div id="bank-rows" class="space-y-3 mb-3">
        @php($banks = old('bank_accounts', $staff?->bankAccounts?->toArray() ?? [['bank_name'=>'','account_name'=>'','account_number'=>'']]))
        @foreach($banks as $i => $b)
        <div class="grid grid-cols-1 md:grid-cols-4 gap-2 items-end">
            <div><label class="block text-xs text-gray-500 mb-1">{{ __('Bank Name') }}</label><input type="text" name="bank_accounts[{{ $i }}][bank_name]" value="{{ $b['bank_name'] ?? '' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
            <div><label class="block text-xs text-gray-500 mb-1">{{ __('Account Name') }}</label><input type="text" name="bank_accounts[{{ $i }}][account_name]" value="{{ $b['account_name'] ?? '' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
            <div><label class="block text-xs text-gray-500 mb-1">{{ __('Account Number') }}</label><input type="text" name="bank_accounts[{{ $i }}][account_number]" value="{{ $b['account_number'] ?? '' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
            <div class="text-sm"><label class="flex items-center gap-1"><input type="radio" name="default_bank" value="{{ $i }}" {{ !empty($b['is_default']) ? 'checked' : '' }}> {{ __('Default') }}</label></div>
        </div>
        @endforeach
    </div>

    <div class="flex items-center justify-between mt-6">
        <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $staff?->is_active ?? true) ? 'checked' : '' }}> {{ __('Active') }}</label>
        <div class="space-x-3">
            <a href="{{ route('admin.staff.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium">{{ __('Save Staff') }}</button>
        </div>
    </div>
</form>
