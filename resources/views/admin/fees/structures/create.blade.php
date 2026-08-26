@extends('layouts.admin')

@section('title', __('Add Fee Structure'))
@section('breadcrumb', __('Fees > Fee Structures > Add'))

@section('content')
<div style="max-width: 960px;">

    {{-- Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">{{ __('Add Fee Structure') }}</h2>
            <p style="font-size: 0.825rem; color: #64748b; margin-top: 2px;">{{ __('Select Form & Stream, then load fee categories to define amounts and breakdowns.') }}</p>
        </div>
        <a href="{{ route('admin.fee-structures.index') }}"
           style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 0.8rem; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; transition: all .15s;"
           onmouseover="this.style.backgroundColor='#f1f5f9'" onmouseout="this.style.backgroundColor='transparent'">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            {{ __('Back') }}
        </a>
    </div>

    @if($errors->any())
    <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.85rem;">
        {{ __('Please fix the errors below.') }}
        <ul style="margin-top: 6px; padding-left: 18px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(session('error'))
    <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.85rem;">
        {{ session('error') }}
    </div>
    @endif

    <form id="feeStructureForm" method="POST" action="{{ route('admin.fee-structures.store') }}">
        @csrf

        {{-- Step 1: Form & Stream Selection --}}
        <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px;">
                <div style="width: 28px; height: 28px; background: #1e293b; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700;">1</div>
                <h3 style="font-size: 1rem; font-weight: 600; color: #334155;">{{ __('Select Form & Stream') }}</h3>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 16px; align-items: end;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Form') }} <span style="color: #ef4444;">*</span></label>
                    <select id="formSelect" name="form_id" required
                            style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff;">
                        <option value="">-- {{ __('Select Form') }} --</option>
                        @foreach($forms as $form)
                            <option value="{{ $form->id }}" {{ old('form_id') == $form->id ? 'selected' : '' }}>{{ $form->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Stream') }}</label>
                    <select id="streamSelect" name="stream_id"
                            style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff;">
                        <option value="">-- {{ __('All Streams') }} --</option>
                    </select>
                </div>

                <div>
                    <button type="button" id="loadCategoriesBtn"
                            style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #0ea5e9; color: #fff; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; white-space: nowrap; transition: all .15s;"
                            onmouseover="this.style.backgroundColor='#0284c7'" onmouseout="this.style.backgroundColor='#0ea5e9'">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        {{ __('Load Fee Categories') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Step 2: Fee Categories Container --}}
        <div id="categoriesContainer" style="display: none;">
            <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 28px; height: 28px; background: #1e293b; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700;">2</div>
                        <h3 style="font-size: 1rem; font-weight: 600; color: #334155;">{{ __('Define Fee Amounts & Breakdowns') }}</h3>
                    </div>
                    <div id="grandTotalBadge" style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 6px 16px; border-radius: 8px; font-size: 0.85rem;">
                        <span style="color: #64748b;">{{ __('Grand Total:') }}</span>
                        <span id="grandTotalValue" style="font-weight: 700; color: #15803d; font-family: monospace; margin-left: 4px;">0 XAF</span>
                    </div>
                </div>

                {{-- Loading Spinner --}}
                <div id="categoriesLoading" style="display: none; text-align: center; padding: 40px;">
                    <svg style="display: inline-block; animation: spin 1s linear infinite; width: 32px; height: 32px; color: #0ea5e9;" fill="none" viewBox="0 0 24 24">
                        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <p style="color: #64748b; font-size: 0.85rem; margin-top: 8px;">{{ __('Loading fee categories...') }}</p>
                </div>

                {{-- Selection Bar --}}
                <div id="selectionBar" style="display: none; margin-bottom: 16px; padding: 10px 16px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 0.82rem; color: #0369a1;">
                            <span id="selectedCount" style="font-weight: 700;">0</span> {{ __('of') }}
                            <span id="totalCatCount">0</span> {{ __('categories selected') }}
                        </span>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" onclick="toggleAllCategories(true)"
                                    style="padding: 4px 12px; font-size: 0.75rem; font-weight: 600; color: #0ea5e9; background: #fff; border: 1px solid #bae6fd; border-radius: 6px; cursor: pointer;">
                                {{ __('Select All') }}
                            </button>
                            <button type="button" onclick="toggleAllCategories(false)"
                                    style="padding: 4px 12px; font-size: 0.75rem; font-weight: 600; color: #64748b; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer;">
                                {{ __('Deselect All') }}
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Category Cards --}}
                <div id="categoryCards" style="display: flex; flex-direction: column; gap: 12px;">
                    {{-- Dynamically filled by JS --}}
                </div>

                {{-- Empty State --}}
                <div id="emptyState" style="display: none; text-align: center; padding: 40px; color: #94a3b8;">
                    <svg style="display: inline-block; width: 48px; height: 48px; margin-bottom: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                    <p style="font-weight: 500;">{{ __('No active fee categories found.') }}</p>
                    <p style="font-size: 0.8rem; margin-top: 4px;">{{ __('Create fee categories first before defining fee structures.') }}</p>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 0;">
                <a href="{{ route('admin.fee-structures.index') }}"
                   style="padding: 10px 20px; font-size: 0.85rem; color: #475569; text-decoration: none; transition: all .15s;"
                   onmouseover="this.style.color='#1e293b'" onmouseout="this.style.color='#475569'">
                    {{ __('Cancel') }}
                </a>
                <button type="submit" id="saveBtn" disabled
                        style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px; background: #1e293b; color: #fff; border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; opacity: 0.5; transition: all .15s;"
                        onmouseover="if(!this.disabled)this.style.backgroundColor='#334155'" onmouseout="this.style.backgroundColor='#1e293b'">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Save Fee Structure') }}
                </button>
            </div>
        </div>
    </form>
</div>

<style>
    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
</style>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = @json(url('admin/fee-structures'));
    const formSelect = document.getElementById('formSelect');
    const streamSelect = document.getElementById('streamSelect');
    const loadBtn = document.getElementById('loadCategoriesBtn');
    const container = document.getElementById('categoriesContainer');
    const cardsDiv = document.getElementById('categoryCards');
    const loadingDiv = document.getElementById('categoriesLoading');
    const emptyDiv = document.getElementById('emptyState');
    const grandTotalVal = document.getElementById('grandTotalValue');
    const saveBtn = document.getElementById('saveBtn');

    let categoriesData = [];
    let existingData = {};

    // ===== Cascading: Form → Stream =====
    formSelect.addEventListener('change', async function() {
        streamSelect.innerHTML = '<option value="">-- {{ __("All Streams") }} --</option>';
        if (!this.value) return;
        try {
            const res = await fetch(`${baseUrl}/streams-by-form/${this.value}`);
            const streams = await res.json();
            streams.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                streamSelect.appendChild(opt);
            });
        } catch(e) { console.error('Error loading streams:', e); }
    });

    // ===== Load Fee Categories =====
    loadBtn.addEventListener('click', async function() {
        if (!formSelect.value) {
            alert('{{ __("Please select a Form first.") }}');
            return;
        }

        container.style.display = 'block';
        loadingDiv.style.display = 'block';
        cardsDiv.style.display = 'none';
        emptyDiv.style.display = 'none';
        cardsDiv.innerHTML = '';

        try {
            // Fetch categories and existing data in parallel
            const [catRes, existRes] = await Promise.all([
                fetch(`${baseUrl}/load-categories`),
                fetch(`${baseUrl}/load-existing?form_id=${formSelect.value}&stream_id=${streamSelect.value || ''}`)
            ]);

            categoriesData = await catRes.json();
            existingData = await existRes.json();

            loadingDiv.style.display = 'none';

            if (categoriesData.length === 0) {
                emptyDiv.style.display = 'block';
                return;
            }

            cardsDiv.style.display = 'flex';
            categoriesData.forEach((cat, idx) => {
                const existing = existingData[cat.id] || null;
                cardsDiv.appendChild(createCategoryCard(cat, idx, existing));
            });

            document.getElementById('selectionBar').style.display = 'block';
            updateSelectedCount();
            updateGrandTotal();
            updateSaveButton();

        } catch(e) {
            console.error('Error loading categories:', e);
            loadingDiv.style.display = 'none';
            emptyDiv.style.display = 'block';
        }
    });

    // ===== Create Category Card =====
    function createCategoryCard(cat, idx, existing) {
        const existingAmount = existing ? parseFloat(existing.amount) : 0;
        const existingBreakdowns = existing && existing.breakdowns ? existing.breakdowns : [];
        const isPreSelected = existingAmount > 0;

        const card = document.createElement('div');
        card.setAttribute('data-cat-idx', idx);
        card.style.cssText = `border: 1px solid ${isPreSelected ? '#bae6fd' : '#e2e8f0'}; border-radius: 10px; overflow: hidden; animation: slideDown 0.3s ease; transition: all .2s; background: #fff;`;

        // Badges
        let badges = '';
        if (cat.is_mandatory) badges += `<span style="display: inline-block; padding: 2px 8px; font-size: 0.7rem; font-weight: 600; border-radius: 9999px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;">{{ __('Mandatory') }}</span>`;
        if (cat.is_tuition) badges += `<span style="display: inline-block; padding: 2px 8px; font-size: 0.7rem; font-weight: 600; border-radius: 9999px; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;">{{ __('Tuition') }}</span>`;
        if (cat.is_boarding) badges += `<span style="display: inline-block; padding: 2px 8px; font-size: 0.7rem; font-weight: 600; border-radius: 9999px; background: #fefce8; color: #ca8a04; border: 1px solid #fef08a;">{{ __('Boarding') }}</span>`;

        card.innerHTML = `
            {{-- Card Header --}}
            <div class="cat-header" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: ${isPreSelected ? '#f0f9ff' : '#f8fafc'}; border-bottom: 1px solid ${isPreSelected ? '#bae6fd' : '#e2e8f0'}; cursor: pointer; transition: background .15s;"
                 onmouseover="this.style.background=this.closest('[data-cat-idx]').querySelector('.cat-toggle').checked ? '#e0f2fe' : '#f1f5f9'"
                 onmouseout="this.style.background=this.closest('[data-cat-idx]').querySelector('.cat-toggle').checked ? '#f0f9ff' : '#f8fafc'">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" class="cat-toggle"
                           ${isPreSelected ? 'checked' : ''}
                           style="width: 18px; height: 18px; accent-color: #0ea5e9; cursor: pointer; flex-shrink: 0;"
                           onclick="event.stopPropagation(); toggleCategory(this.closest('[data-cat-idx]'), this.checked);">
                    <div>
                        <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;">${cat.name}</span>
                        <span style="color: #94a3b8; font-size: 0.75rem; margin-left: 6px;">(${cat.code})</span>
                    </div>
                    <div style="display: flex; gap: 4px; margin-left: 8px;">${badges}</div>
                </div>
                <span class="cat-total-badge" style="font-family: monospace; font-weight: 700; color: #1e293b; font-size: 0.9rem; ${isPreSelected ? '' : 'display: none;'}">${existingAmount > 0 ? numberFormat(existingAmount) + ' XAF' : '0 XAF'}</span>
            </div>

            {{-- Card Body (hidden by default, shown when selected) --}}
            <div class="card-body" style="padding: 18px; display: ${isPreSelected ? 'block' : 'none'};">
                <input type="hidden" class="cat-id-input" name="${isPreSelected ? 'fees[' + idx + '][category_id]' : ''}" value="${cat.id}" data-field-name="fees[${idx}][category_id]">

                <div style="display: grid; grid-template-columns: 1fr; gap: 16px;">
                    {{-- Amount --}}
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Amount (XAF)') }} <span style="color: #ef4444;">*</span></label>
                        <input type="number" class="fee-amount-input" data-field-name="fees[${idx}][amount]"
                               name="${isPreSelected ? 'fees[' + idx + '][amount]' : ''}"
                               value="${existingAmount || ''}"
                               min="0" step="100" placeholder="0"
                               style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.95rem; font-family: monospace; text-align: right; outline: none; transition: border-color .15s;"
                               onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                               onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                               oninput="updateCardTotal(this); updateGrandTotal(); updateSaveButton();">
                    </div>

                    {{-- Fee Breakdown Section --}}
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <label style="font-size: 0.8rem; font-weight: 600; color: #374151;">{{ __('Fee Breakdown') }}</label>
                            <button type="button" class="add-breakdown-btn"
                                    style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px; font-size: 0.75rem; font-weight: 600; color: #0ea5e9; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; cursor: pointer; transition: all .15s;"
                                    onmouseover="this.style.background='#e0f2fe'" onmouseout="this.style.background='#f0f9ff'"
                                    onclick="addBreakdownRow(this.closest('[data-cat-idx]'))">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                {{ __('Add Item') }}
                            </button>
                        </div>

                        <div class="breakdown-rows" style="display: flex; flex-direction: column; gap: 8px;">
                            {{-- Breakdown rows inserted here --}}
                        </div>

                        <div class="breakdown-summary" style="display: none; margin-top: 10px; padding: 8px 12px; background: #f8fafc; border-radius: 6px; border: 1px dashed #cbd5e1;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 0.78rem; color: #64748b;">{{ __('Breakdown Total:') }}</span>
                                <span class="breakdown-total-value" style="font-family: monospace; font-weight: 600; color: #334155; font-size: 0.85rem;">0 XAF</span>
                            </div>
                            <div class="breakdown-mismatch" style="display: none; margin-top: 4px; font-size: 0.75rem; color: #dc2626;">
                                <svg style="display: inline-block; width: 14px; height: 14px; vertical-align: middle; margin-right: 2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                {{ __('Breakdown total does not match the fee amount.') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Click header (outside checkbox) to toggle
        card.querySelector('.cat-header').addEventListener('click', function(e) {
            if (e.target.classList.contains('cat-toggle')) return;
            const cb = card.querySelector('.cat-toggle');
            cb.checked = !cb.checked;
            toggleCategory(card, cb.checked);
        });

        // Pre-populate existing breakdowns
        if (existingBreakdowns.length > 0) {
            const rowsContainer = card.querySelector('.breakdown-rows');
            existingBreakdowns.forEach((bd, bdIdx) => {
                rowsContainer.appendChild(createBreakdownRow(idx, bdIdx, bd.name, parseFloat(bd.amount), isPreSelected));
            });
            updateBreakdownSummary(card);
        }

        return card;
    }

    // ===== Toggle Category Selection =====
    window.toggleCategory = function(card, selected) {
        const body = card.querySelector('.card-body');
        const header = card.querySelector('.cat-header');
        const badge = card.querySelector('.cat-total-badge');
        const catIdInput = card.querySelector('.cat-id-input');
        const amountInput = card.querySelector('.fee-amount-input');

        if (selected) {
            body.style.display = 'block';
            card.style.borderColor = '#bae6fd';
            header.style.background = '#f0f9ff';
            header.style.borderBottomColor = '#bae6fd';
            badge.style.display = 'inline';
            // Enable form fields
            catIdInput.name = catIdInput.getAttribute('data-field-name');
            amountInput.name = amountInput.getAttribute('data-field-name');
            card.querySelectorAll('.breakdown-rows input').forEach(inp => {
                if (inp.getAttribute('data-field-name')) inp.name = inp.getAttribute('data-field-name');
            });
            // Focus amount input
            setTimeout(() => amountInput.focus(), 100);
        } else {
            body.style.display = 'none';
            card.style.borderColor = '#e2e8f0';
            header.style.background = '#f8fafc';
            header.style.borderBottomColor = '#e2e8f0';
            badge.style.display = 'none';
            // Disable form fields (remove name so they're not submitted)
            catIdInput.name = '';
            amountInput.name = '';
            card.querySelectorAll('.breakdown-rows input').forEach(inp => {
                inp.setAttribute('data-field-name', inp.name);
                inp.name = '';
            });
        }

        updateSelectedCount();
        updateGrandTotal();
        updateSaveButton();
    };

    // ===== Toggle All Categories =====
    window.toggleAllCategories = function(selectAll) {
        document.querySelectorAll('[data-cat-idx]').forEach(card => {
            const cb = card.querySelector('.cat-toggle');
            if (cb.checked !== selectAll) {
                cb.checked = selectAll;
                toggleCategory(card, selectAll);
            }
        });
    };

    // ===== Update Selected Count =====
    function updateSelectedCount() {
        const total = document.querySelectorAll('.cat-toggle').length;
        const selected = document.querySelectorAll('.cat-toggle:checked').length;
        const countEl = document.getElementById('selectedCount');
        const totalEl = document.getElementById('totalCatCount');
        if (countEl) countEl.textContent = selected;
        if (totalEl) totalEl.textContent = total;
    }

    // ===== Breakdown Row =====
    function createBreakdownRow(catIdx, bdIdx, name = '', amount = '', active = true) {
        const row = document.createElement('div');
        row.style.cssText = 'display: grid; grid-template-columns: 1fr 160px 36px; gap: 8px; align-items: center; animation: slideDown .2s ease;';
        const nameFieldName = `fees[${catIdx}][breakdowns][${bdIdx}][name]`;
        const amtFieldName = `fees[${catIdx}][breakdowns][${bdIdx}][amount]`;
        row.innerHTML = `
            <input type="text" name="${active ? nameFieldName : ''}" data-field-name="${nameFieldName}" value="${escapeHtml(name)}"
                   placeholder="{{ __('Item name (e.g. Lab fee)') }}"
                   style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.82rem; outline: none;"
                   onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#d1d5db'">
            <input type="number" name="${active ? amtFieldName : ''}" data-field-name="${amtFieldName}" value="${amount}"
                   min="0" step="100" placeholder="0"
                   class="breakdown-amount-input"
                   style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.82rem; font-family: monospace; text-align: right; outline: none;"
                   onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#d1d5db'"
                   oninput="updateBreakdownSummary(this.closest('[data-cat-idx]'))">
            <button type="button"
                    style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border: 1px solid #fecaca; background: #fef2f2; border-radius: 6px; color: #dc2626; cursor: pointer; transition: all .15s;"
                    onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'"
                    onclick="removeBreakdownRow(this)">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        `;
        return row;
    }

    // ===== Add Breakdown Row =====
    window.addBreakdownRow = function(card) {
        const catIdx = card.getAttribute('data-cat-idx');
        const rowsContainer = card.querySelector('.breakdown-rows');
        const bdIdx = rowsContainer.children.length;
        const isActive = card.querySelector('.cat-toggle').checked;
        rowsContainer.appendChild(createBreakdownRow(catIdx, bdIdx, '', '', isActive));
        updateBreakdownSummary(card);
        // Focus the new name input
        const newRow = rowsContainer.lastElementChild;
        newRow.querySelector('input[type="text"]').focus();
    };

    // ===== Remove Breakdown Row =====
    window.removeBreakdownRow = function(btn) {
        const card = btn.closest('[data-cat-idx]');
        const row = btn.parentElement;
        row.remove();
        reindexBreakdowns(card);
        updateBreakdownSummary(card);
    };

    // ===== Reindex Breakdown names after removal =====
    function reindexBreakdowns(card) {
        const catIdx = card.getAttribute('data-cat-idx');
        const isActive = card.querySelector('.cat-toggle').checked;
        const rows = card.querySelectorAll('.breakdown-rows > div');
        rows.forEach((row, i) => {
            const nameInput = row.querySelector('input[type="text"]');
            const amountInput = row.querySelector('input[type="number"]');
            const nameField = `fees[${catIdx}][breakdowns][${i}][name]`;
            const amtField = `fees[${catIdx}][breakdowns][${i}][amount]`;
            if (nameInput) { nameInput.setAttribute('data-field-name', nameField); nameInput.name = isActive ? nameField : ''; }
            if (amountInput) { amountInput.setAttribute('data-field-name', amtField); amountInput.name = isActive ? amtField : ''; }
        });
    }

    // ===== Update Breakdown Summary =====
    window.updateBreakdownSummary = function(card) {
        const rows = card.querySelectorAll('.breakdown-amount-input');
        const summary = card.querySelector('.breakdown-summary');
        const totalSpan = card.querySelector('.breakdown-total-value');
        const mismatch = card.querySelector('.breakdown-mismatch');
        const feeAmount = parseFloat(card.querySelector('.fee-amount-input').value) || 0;

        let total = 0;
        rows.forEach(inp => total += parseFloat(inp.value) || 0);

        if (rows.length === 0) {
            summary.style.display = 'none';
            return;
        }

        summary.style.display = 'block';
        totalSpan.textContent = numberFormat(total) + ' XAF';

        if (feeAmount > 0 && total > 0 && Math.abs(total - feeAmount) > 0.01) {
            mismatch.style.display = 'block';
            totalSpan.style.color = '#dc2626';
        } else {
            mismatch.style.display = 'none';
            totalSpan.style.color = '#334155';
        }
    };

    // ===== Update Card Total Badge =====
    window.updateCardTotal = function(input) {
        const card = input.closest('[data-cat-idx]');
        const badge = card.querySelector('.cat-total-badge');
        const val = parseFloat(input.value) || 0;
        badge.textContent = numberFormat(val) + ' XAF';
        updateBreakdownSummary(card);
    };

    // ===== Update Grand Total (only selected categories) =====
    window.updateGrandTotal = function() {
        let total = 0;
        document.querySelectorAll('[data-cat-idx]').forEach(card => {
            if (card.querySelector('.cat-toggle').checked) {
                const inp = card.querySelector('.fee-amount-input');
                total += parseFloat(inp.value) || 0;
            }
        });
        grandTotalVal.textContent = numberFormat(total) + ' XAF';
    };

    // ===== Update Save Button (need at least 1 selected with amount) =====
    window.updateSaveButton = function() {
        let hasAmount = false;
        document.querySelectorAll('[data-cat-idx]').forEach(card => {
            if (card.querySelector('.cat-toggle').checked) {
                const inp = card.querySelector('.fee-amount-input');
                if (parseFloat(inp.value) > 0) hasAmount = true;
            }
        });
        saveBtn.disabled = !hasAmount;
        saveBtn.style.opacity = hasAmount ? '1' : '0.5';
    };

    // ===== Helper: Number format =====
    function numberFormat(n) {
        return Math.round(n).toLocaleString('en-US');
    }

    // ===== Helper: Escape HTML =====
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ===== Trigger form load if returning with old input =====
    @if(old('form_id'))
        formSelect.dispatchEvent(new Event('change'));
    @endif
});
</script>
@endpush
