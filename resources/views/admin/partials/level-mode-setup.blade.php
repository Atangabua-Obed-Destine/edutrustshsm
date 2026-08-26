{{--
    Forced-setup modal: appears on every admin page until the installation's
    School Level Mode is chosen. Branded EduTrust — powered by I-NNOVA.
    Only shown to users who can set it (super_admin / admin).
--}}
@if(!\App\Support\LevelContext::isConfigured() && in_array(auth()->user()->role ?? '', ['super_admin', 'admin']))
<div id="level-mode-modal" class="fixed inset-0 z-[100] flex items-center justify-center p-4"
     style="background: rgba(15,23,42,0.75); backdrop-filter: blur(2px);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden">
        {{-- Branded header --}}
        <div class="px-6 py-5 text-white" style="background: linear-gradient(120deg,#1e293b,#0f766e);">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center text-xl font-bold">E</div>
                <div>
                    <h2 class="text-lg font-bold leading-tight">{{ __('Welcome to') }} {{ \App\Models\SchoolSetting::current()->school_name ?? 'EduTrustSchool' }}</h2>
                    <p class="text-xs text-white/70">{{ __('powered by') }} <span class="font-semibold tracking-wide">I-NNOVA</span></p>
                </div>
            </div>
        </div>

        <div class="p-6">
            <h3 class="text-base font-semibold text-gray-800">{{ __('Choose your School Level Mode') }}</h3>
            <p class="text-sm text-gray-500 mt-1">{{ __('Tell EduTrust which levels your school runs. The whole system adapts to your choice. You can change this later in Settings.') }}</p>

            <form method="POST" action="{{ route('admin.settings.level-mode') }}" class="mt-5 space-y-3">
                @csrf
                @php($modes = [
                    'nursery_primary' => [__('Nursery / Primary only'), __('Nursery 1–3 and Class 1–6')],
                    'secondary' => [__('Secondary / High School only'), __('Form 1–5 and Lower/Upper Sixth')],
                    'both' => [__('Both'), __('A combined school running nursery/primary and secondary')],
                ])
                @foreach($modes as $value => [$title, $desc])
                <label class="flex items-start gap-3 border border-gray-200 rounded-xl p-4 cursor-pointer hover:border-teal-500 hover:bg-teal-50/40 transition">
                    <input type="radio" name="school_level_mode" value="{{ $value }}" class="mt-1" required>
                    <span>
                        <span class="block text-sm font-semibold text-gray-800">{{ $title }}</span>
                        <span class="block text-xs text-gray-500">{{ $desc }}</span>
                    </span>
                </label>
                @endforeach

                <button type="submit" class="w-full mt-2 text-white px-6 py-2.5 rounded-lg text-sm font-semibold" style="background: linear-gradient(120deg,#0f766e,#1e293b);">
                    {{ __('Confirm & Continue') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endif
