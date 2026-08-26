# ScholarTrack School Management System - Development Prompt (PART 3)

**Continuation from Part 2 - Sections 6-11: Core Operational Modules**

---

## 6. FEE MANAGEMENT MODULE

### 6.1 Module Overview

The Fee Management module handles the complete financial lifecycle of student fees from configuration through payment tracking and clearance enforcement. It integrates with EduTrustPay for external payments and supports internal payment recording.

**Key Features:**
- Fee structure configuration per form/residence type/session
- Automatic and manual fee assignment
- Payment recording (Cash, Bank Transfer, MoMo, EduTrustPay sync)
- Fee clearance enforcement (block report cards, transcripts)
- Discount and fine management
- Fee waivers and exemptions
- Comprehensive payment history

---

### 6.2 Fee Categories Management

**6.2.1 Create Fee Category**
```
┌────────────────────────────────────────────────────────┐
│ CREATE FEE CATEGORY                                    │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Category Name: [Tuition Fee________________]  *       │
│                                                        │
│ Code:          [TUI_____]  *                          │
│                (Short code for reference)              │
│                                                        │
│ Description:                                           │
│ ┌────────────────────────────────────────────────┐   │
│ │ Main instructional fee covering teaching,      │   │
│ │ classroom materials, and basic facilities.     │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ Properties:                                            │
│ ☑ Mandatory (All students must pay)                   │
│ ☐ Refundable (Can be refunded if student withdraws)   │
│                                                        │
│ Status:        ⚪ Active  ⚪ Inactive                  │
│                                                        │
│ [CANCEL]  [SAVE CATEGORY]                             │
└────────────────────────────────────────────────────────┘
```

**6.2.2 Fee Categories List**
```
FEE CATEGORIES
────────────────────────────────────────────────────────────
Category           Code    Mandatory  Refundable  Status
────────────────────────────────────────────────────────────
Tuition Fee        TUI     Yes        No          Active
Exam Fee           EXAM    Yes        No          Active
Library & ICT      LIB     Yes        No          Active
Boarding Fee       BOARD   Yes        Partial     Active
Sports & Culture   SPORT   Yes        No          Active
PTA Levy           PTA     Yes        No          Active
Uniform (One-time) UNI     No         No          Active
Lab Fee (Science)  LAB     No         No          Active
────────────────────────────────────────────────────────────
[+ CREATE FEE CATEGORY]
```

**Common Fee Categories in Cameroon Schools:**
- **Tuition Fee** - Main instructional charge
- **Exam Fee** - Internal exams (sequence papers, marking)
- **Library & ICT Fee** - Library and computer lab access
- **Boarding Fee** - For boarding students (accommodation + meals)
- **Half-Boarding Fee** - For half-boarding (lunch only)
- **Sports & Culture Fee** - Sports equipment, cultural activities
- **PTA Levy** - Parent-Teacher Association contribution
- **Lab Fee** - Science practical materials (Forms 4-7)
- **Registration Fee** - One-time fee for new students
- **Uniform Fee** - School uniform (typically one-time)
- **Medical Fee** - School clinic, first aid
- **Transport Fee** - School bus (if available)
- **GCE Registration** - External exam fee (Form 5, Upper Sixth)

---

### 6.3 Fee Structure Configuration

**Purpose:** Define how much each fee category costs per form/residence type

**6.3.1 Configure Fee Structure (by Form & Residence Type)**
```
┌────────────────────────────────────────────────────────────────┐
│ CONFIGURE FEE STRUCTURE                                        │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ Session:       [2024/2025 ▼]  *                               │
│ Form:          [Form 3 ▼]  *                                   │
│ Residence Type:[Day Student ▼]  *                              │
│                                                                │
│ ──────────────────────────────────────────────────────         │
│ FEE BREAKDOWN (Per Term)                                       │
│ ──────────────────────────────────────────────────────         │
│                                                                │
│ Fee Category         Amount (XAF)    Frequency                 │
│ ────────────────────────────────────────────────────           │
│ Tuition Fee          [50,000__]     Per Term                   │
│ Exam Fee             [5,000___]     Per Term                   │
│ Library & ICT        [3,000___]     Per Term                   │
│ Sports & Culture     [2,000___]     Per Term                   │
│ PTA Levy             [1,000___]     Per Term                   │
│                                                                │
│ [+ ADD FEE CATEGORY]                                           │
│                                                                │
│ ──────────────────────────────────────────────────────         │
│ TOTAL PER TERM:      61,000 XAF                                │
│ TOTAL PER YEAR (×3): 183,000 XAF                               │
│ ──────────────────────────────────────────────────────         │
│                                                                │
│ [CANCEL]  [SAVE FEE STRUCTURE]                                 │
└────────────────────────────────────────────────────────────────┘
```

**6.3.2 Copy Fee Structure (Time-Saver)**
```
┌────────────────────────────────────────────────────────┐
│ COPY FEE STRUCTURE                                     │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Copy FROM:                                             │
│ Session:       [2023/2024 ▼]                          │
│ Form:          [Form 3 ▼]                             │
│ Residence:     [Day Student ▼]                        │
│                                                        │
│ Copy TO:                                               │
│ Session:       [2024/2025 ▼]                          │
│ Form:          [Form 3 ▼]                             │
│ Residence:     [Day Student ▼]                        │
│                                                        │
│ ☑ Adjust amounts by percentage: [5___]%               │
│   (e.g., 5% increase from previous year)              │
│                                                        │
│ This will copy:                                        │
│ • All fee categories                                   │
│ • Amounts (with adjustment if selected)                │
│ • Frequency settings                                   │
│                                                        │
│ [CANCEL]  [COPY FEE STRUCTURE]                        │
└────────────────────────────────────────────────────────┘
```

**6.3.3 Fee Structure Matrix View (Overview)**
```
FEE STRUCTURE MATRIX - SESSION 2024/2025
(All amounts per term in XAF)

Form      Day Student  Boarding     Half-Boarding  Actions
────────────────────────────────────────────────────────────
Form 1    56,000      116,000      76,000         [Edit] [Copy]
Form 2    58,000      118,000      78,000         [Edit] [Copy]
Form 3    61,000      121,000      81,000         [Edit] [Copy]
Form 4    70,000      135,000      95,000         [Edit] [Copy]
Form 5    75,000      140,000      100,000        [Edit] [Copy]
Lower 6   85,000      155,000      110,000        [Edit] [Copy]
Upper 6   90,000      160,000      115,000        [Edit] [Copy]
────────────────────────────────────────────────────────────

[BULK EDIT]  [IMPORT FROM EXCEL]  [EXPORT TO EXCEL]
```

**Breakdown Example (Form 3, Day Student):**
```
┌─────────────────────────────────────────────────────┐
│ FORM 3 - DAY STUDENT FEE BREAKDOWN                 │
├─────────────────────────────────────────────────────┤
│                                                     │
│ Per Term:                                           │
│ • Tuition Fee:      50,000 XAF                     │
│ • Exam Fee:          5,000 XAF                     │
│ • Library & ICT:     3,000 XAF                     │
│ • Sports & Culture:  2,000 XAF                     │
│ • PTA Levy:          1,000 XAF                     │
│ ─────────────────────────                          │
│ Subtotal:           61,000 XAF                     │
│                                                     │
│ Per Year (3 terms):                                 │
│ Total:             183,000 XAF                     │
│                                                     │
│ [VIEW DETAILED BREAKDOWN]                           │
└─────────────────────────────────────────────────────┘
```

---

### 6.4 Fee Assignment

**6.4.1 Automatic Fee Assignment Triggers**

Fees are automatically assigned when:

**Trigger 1: Student Enrollment**
```
SCENARIO: New student John Doe enrolls in Form 3A (Day Student)
ACTION: System automatically assigns Term 1 fees
RESULT:
┌─────────────────────────────────────────────────────┐
│ AUTO-ASSIGNED FEES: John Doe (SEC/2024/045)        │
├─────────────────────────────────────────────────────┤
│ Session: 2024/2025                                  │
│ Term: First Term                                    │
│ Class: Form 3A (Day Student)                        │
│                                                     │
│ Fees Assigned:                                      │
│ • Tuition Fee:      50,000 XAF (Due: 01/10/2024)  │
│ • Exam Fee:          5,000 XAF (Due: 01/10/2024)  │
│ • Library & ICT:     3,000 XAF (Due: 01/10/2024)  │
│ • Sports & Culture:  2,000 XAF (Due: 01/10/2024)  │
│ • PTA Levy:          1,000 XAF (Due: 01/10/2024)  │
│ ─────────────────────────                          │
│ TOTAL:              61,000 XAF                     │
│                                                     │
│ ✓ Fees assigned successfully                       │
│ ✓ Parent notified via email/SMS                    │
└─────────────────────────────────────────────────────┘
```

**Trigger 2: New Term Starts**
```
SCENARIO: Second Term begins (06/01/2025)
ACTION: System auto-assigns Term 2 fees to ALL active students
RESULT:
┌─────────────────────────────────────────────────────┐
│ BULK FEE ASSIGNMENT - SECOND TERM 2024/2025        │
├─────────────────────────────────────────────────────┤
│                                                     │
│ Processing: 1,189 active students                   │
│                                                     │
│ ████████████████████████████████  100%             │
│                                                     │
│ ✓ Fees assigned to 1,189 students                  │
│ ✓ Total fees assigned: 72,579,000 XAF             │
│ ✓ Email notifications sent to 1,050 parents        │
│ ✓ SMS notifications sent to 139 parents (no email) │
│                                                     │
│ [VIEW ASSIGNMENT LOG]  [CLOSE]                     │
└─────────────────────────────────────────────────────┘
```

**Trigger 3: Student Changes Residence Type**
```
SCENARIO: Mary Tanyi switches from Day to Boarding (mid-year)
ACTION: System recalculates fees for remaining terms
RESULT:
┌─────────────────────────────────────────────────────┐
│ FEE RECALCULATION: Mary Tanyi (SEC/2024/046)       │
├─────────────────────────────────────────────────────┤
│                                                     │
│ Residence Change:                                   │
│ From: Day Student                                   │
│ To:   Boarding Student                              │
│ Effective: Start of Second Term (06/01/2025)       │
│                                                     │
│ PREVIOUS FEES (Day Student):                        │
│ • Term 1: 61,000 XAF (Already paid)               │
│ • Term 2: 61,000 XAF (Pending)                    │
│ • Term 3: 61,000 XAF (Pending)                    │
│                                                     │
│ NEW FEES (Boarding Student):                        │
│ • Term 1: 61,000 XAF (No change - already paid)   │
│ • Term 2: 121,000 XAF (Updated ✓)                 │
│ • Term 3: 121,000 XAF (Updated ✓)                 │
│                                                     │
│ ADDITIONAL CHARGES:                                 │
│ Term 2 difference: +60,000 XAF                     │
│ Term 3 difference: +60,000 XAF                     │
│ ─────────────────────────                          │
│ Total Additional: 120,000 XAF                      │
│                                                     │
│ ✓ Fees updated                                     │
│ ✓ Parent notified of new balance                  │
│                                                     │
│ [VIEW UPDATED FEE SUMMARY]                          │
└─────────────────────────────────────────────────────┘
```

**Trigger 4: Student Promoted to Next Form**
```
SCENARIO: End of year - John promoted Form 3 → Form 4 Science
ACTION: Fees reassigned based on Form 4 fee structure
RESULT:
┌─────────────────────────────────────────────────────┐
│ FEE REASSIGNMENT: John Doe (SEC/2024/045)          │
├─────────────────────────────────────────────────────┤
│                                                     │
│ Promotion: Form 3A → Form 4 Science A               │
│ Session: 2025/2026 (New academic year)             │
│                                                     │
│ PREVIOUS FEES (Form 3, Day):                        │
│ Per Term: 61,000 XAF                               │
│                                                     │
│ NEW FEES (Form 4 Science, Day):                     │
│ Per Term: 70,000 XAF (+9,000 increase)             │
│                                                     │
│ NEW FEE BREAKDOWN (Term 1):                         │
│ • Tuition Fee:      55,000 XAF                     │
│ • Exam Fee:          5,000 XAF                     │
│ • Lab Fee (Science): 5,000 XAF (NEW)               │
│ • Library & ICT:     3,000 XAF                     │
│ • Sports & Culture:  2,000 XAF                     │
│ ─────────────────────────                          │
│ TOTAL:              70,000 XAF                     │
│                                                     │
│ Due Date: 15/09/2025                               │
│                                                     │
│ ✓ Fees assigned for new session                   │
│ ✓ Parent notified of fee increase                 │
│                                                     │
│ [VIEW FULL FEE SCHEDULE]                            │
└─────────────────────────────────────────────────────┘
```

---

**6.4.2 Manual Fee Assignment**

For special cases (one-off fees, missed assignments, corrections):

```
┌────────────────────────────────────────────────────────┐
│ MANUALLY ASSIGN FEE                                    │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Student:       [Search Student ▼_______________]      │
│                John Doe (SEC/2024/045) - Form 3A       │
│                                                        │
│ Session:       [2024/2025 ▼]                          │
│ Term:          [First Term ▼]                         │
│                (Leave blank for yearly fees)           │
│                                                        │
│ Fee Category:  [Select Category ▼______________]      │
│                                                        │
│ Amount:        [_________] XAF  *                     │
│                                                        │
│ Due Date:      [DD/MM/YYYY] 📅  *                     │
│                                                        │
│ Reason/Notes:                                          │
│ ┌────────────────────────────────────────────────┐   │
│ │ e.g., Late registration fee, Replacement ID    │   │
│ │ card, Extra tutorial classes, etc.             │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ [CANCEL]  [ASSIGN FEE]                                │
└────────────────────────────────────────────────────────┘
```

**Example Use Cases:**
- **Replacement ID Card:** 2,000 XAF one-time fee
- **Lost Library Book:** 5,000 XAF replacement cost
- **Extra Tutorial Classes:** 20,000 XAF per term
- **Late Registration Penalty:** 10,000 XAF one-time
- **Transcript Fee:** 5,000 XAF per copy
- **Transfer Certificate Fee:** 3,000 XAF one-time

---

**6.4.3 Bulk Fee Assignment (Custom)**

```
┌────────────────────────────────────────────────────────┐
│ BULK ASSIGN FEE TO MULTIPLE STUDENTS                   │
├────────────────────────────────────────────────────────┤
│                                                        │
│ SELECT STUDENTS:                                       │
│ ──────────────────────────────────────────────        │
│ ⚪ All students in a class section                    │
│    Class Section: [Form 5 Science ▼]                  │
│    (35 students)                                       │
│                                                        │
│ ⚪ All students in a form                              │
│    Form: [Form 5 ▼]                                   │
│    (95 students across all streams/sections)           │
│                                                        │
│ ⚪ Custom selection (upload student IDs)               │
│    [Choose CSV File] 📁                               │
│                                                        │
│ ──────────────────────────────────────────────        │
│ FEE DETAILS:                                           │
│ ──────────────────────────────────────────────        │
│ Session:       [2024/2025 ▼]                          │
│ Term:          [Second Term ▼]                        │
│ Fee Category:  [GCE O-Level Registration ▼]           │
│ Amount:        [35,000___] XAF                        │
│ Due Date:      [15/02/2025] 📅                        │
│                                                        │
│ Reason:                                                │
│ ┌────────────────────────────────────────────────┐   │
│ │ GCE O-Level external exam registration fee    │   │
│ │ for 2024/2025 academic year                    │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ PREVIEW:                                               │
│ 35 students will be assigned 35,000 XAF each          │
│ Total fees assigned: 1,225,000 XAF                    │
│                                                        │
│ ☑ Send email notification to parents                  │
│                                                        │
│ [CANCEL]  [ASSIGN TO ALL STUDENTS]                    │
└────────────────────────────────────────────────────────┘
```

---

### 6.5 Fee Discounts & Waivers

**6.5.1 Apply Discount to Individual Student**
```
┌────────────────────────────────────────────────────────┐
│ APPLY DISCOUNT: John Doe (SEC/2024/045)                │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Current Fee Balance: 61,000 XAF                        │
│                                                        │
│ DISCOUNT TYPE:                                         │
│ ⚪ Percentage Discount                                 │
│    Amount: [10___]% off total fees                    │
│    = 6,100 XAF discount                                │
│                                                        │
│ ⚪ Fixed Amount Discount                               │
│    Amount: [_____] XAF off total fees                 │
│                                                        │
│ Apply To:                                              │
│ ⚪ All fees for this term                              │
│ ⚪ Specific fee category: [Tuition Fee ▼]              │
│                                                        │
│ Reason:                                                │
│ ⚪ Scholarship                                         │
│ ⚪ Staff Child                                         │
│ ⚪ Sibling Discount                                    │
│ ⚪ Financial Hardship                                  │
│ ⚪ Merit/Excellence Award                              │
│ ⚪ Other: [Specify______________]                      │
│                                                        │
│ Description:                                           │
│ ┌────────────────────────────────────────────────┐   │
│ │ Merit scholarship for top 3 students in Form 3 │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ Valid For:                                             │
│ ⚪ This term only                                      │
│ ⚪ Entire session (all 3 terms)                        │
│ ⚪ Custom period: From [___] To [___]                  │
│                                                        │
│ NEW BALANCE AFTER DISCOUNT:                            │
│ Original: 61,000 XAF                                   │
│ Discount: -6,100 XAF (10%)                             │
│ ─────────────────────                                 │
│ New Total: 54,900 XAF                                  │
│                                                        │
│ [CANCEL]  [APPLY DISCOUNT]                            │
└────────────────────────────────────────────────────────┘
```

**6.5.2 Sibling Discount (Automatic)**

System automatically detects siblings by matching:
- Guardian phone numbers
- Guardian email addresses
- Home address

```
┌────────────────────────────────────────────────────────┐
│ 👨‍👩‍👧‍👦 SIBLING DISCOUNT DETECTED                            │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Guardian: Jane Doe (Phone: 670123456)                  │
│                                                        │
│ Children Enrolled:                                     │
│ 1. John Doe (SEC/2024/045) - Form 3A                  │
│ 2. Mary Doe (SEC/2023/012) - Form 5 Science           │
│ 3. Peter Doe (SEC/2022/008) - Lower Sixth             │
│                                                        │
│ DISCOUNT POLICY (Configurable):                        │
│ • 1st child: No discount                               │
│ • 2nd child: 5% off tuition                            │
│ • 3rd+ child: 10% off tuition                          │
│                                                        │
│ AUTOMATIC DISCOUNTS APPLIED:                           │
│ ──────────────────────────────────────────────        │
│ Mary Doe (2nd child):                                  │
│ • Original Tuition: 60,000 XAF                         │
│ • Discount: -3,000 XAF (5%)                            │
│ • New Tuition: 57,000 XAF                              │
│                                                        │
│ Peter Doe (3rd child):                                 │
│ • Original Tuition: 70,000 XAF                         │
│ • Discount: -7,000 XAF (10%)                           │
│ • New Tuition: 63,000 XAF                              │
│                                                        │
│ Total Family Savings: 10,000 XAF per term              │
│                                                        │
│ ✓ Discounts applied automatically                     │
│ ☐ Notify parent of sibling discount benefits          │
│                                                        │
│ [REVIEW]  [CONFIRM]                                   │
└────────────────────────────────────────────────────────┘
```

**6.5.3 Fee Waiver (Full or Partial)**
```
┌────────────────────────────────────────────────────────┐
│ WAIVE FEE: Mary Tanyi (SEC/2024/046)                   │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Current Outstanding Fees: 121,000 XAF                  │
│                                                        │
│ Select Fee(s) to Waive:                                │
│ ☑ Tuition Fee - 100,000 XAF                           │
│ ☑ Boarding Fee - 15,000 XAF                           │
│ ☐ Exam Fee - 5,000 XAF                                │
│ ☐ Library & ICT - 3,000 XAF                           │
│                                                        │
│ Total to Waive: 115,000 XAF                            │
│                                                        │
│ Waiver Type:                                           │
│ ⚪ Full Waiver (100% - no payment required)            │
│ ⚪ Partial Waiver: [50___]%                            │
│                                                        │
│ Reason:                                                │
│ ⚪ Full Scholarship                                    │
│ ⚪ Orphan/Vulnerable Child                             │
│ ⚪ Staff Child                                         │
│ ⚪ Financial Hardship (approved by Principal)          │
│ ⚪ Other: [Specify______________]                      │
│                                                        │
│ Authorization:                                         │
│ Approved By: [Principal ▼]                            │
│ Approval Date: [15/10/2024] 📅                        │
│                                                        │
│ Supporting Documents:                                  │
│ [Upload Scholarship Letter] 📁                        │
│                                                        │
│ Notes:                                                 │
│ ┌────────────────────────────────────────────────┐   │
│ │ Student received full scholarship from XYZ     │   │
│ │ Foundation covering tuition and boarding.      │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ NEW BALANCE AFTER WAIVER:                              │
│ Original: 121,000 XAF                                  │
│ Waived: -115,000 XAF                                   │
│ ─────────────────────                                 │
│ Remaining: 6,000 XAF (Exam + Library fees)             │
│                                                        │
│ [CANCEL]  [APPLY WAIVER]                              │
└────────────────────────────────────────────────────────┘
```

---

### 6.6 Payment Recording

**6.6.1 Record Cash Payment (School Office)**
```
┌────────────────────────────────────────────────────────┐
│ RECORD PAYMENT - CASH                                  │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Student:       [Search Student ▼_______________]      │
│                John Doe (SEC/2024/045) - Form 3A       │
│                Outstanding Balance: 61,000 XAF         │
│                                                        │
│ Payment Details:                                       │
│ ──────────────────────────────────────────────        │
│ Amount Paid:   [61,000___] XAF  *                     │
│                                                        │
│ Payment Date:  [15/10/2024] 📅  *                     │
│ Payment Time:  [14:30_____] ⏰                        │
│                                                        │
│ Payment Method: Cash ✓                                │
│                                                        │
│ Received By:   [Mrs. Ayuk (Bursar) ▼]                 │
│                                                        │
│ ALLOCATION:                                            │
│ ──────────────────────────────────────────────        │
│ ☑ Tuition Fee: 50,000 XAF                             │
│ ☑ Exam Fee: 5,000 XAF                                 │
│ ☑ Library & ICT: 3,000 XAF                            │
│ ☑ Sports & Culture: 2,000 XAF                         │
│ ☑ PTA Levy: 1,000 XAF                                 │
│                                                        │
│ Total Allocated: 61,000 XAF ✓                         │
│                                                        │
│ Payer Information:                                     │
│ ──────────────────────────────────────────────        │
│ Name:          [Jane Doe (Mother)___________]         │
│ Phone:         [+237 670 123 456___________]          │
│ Email:         [jane.doe@example.com_______]          │
│                                                        │
│ Receipt Number: [AUTO: RCP/2024/00123_____]           │
│                                                        │
│ Notes:                                                 │
│ ┌────────────────────────────────────────────────┐   │
│ │ Full payment for First Term                    │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ ☑ Print receipt immediately                           │
│ ☑ Send receipt to parent's email                      │
│                                                        │
│ [CANCEL]  [RECORD PAYMENT & GENERATE RECEIPT]         │
└────────────────────────────────────────────────────────┘
```

**Post-Payment Actions:**
1. Payment record created in database
2. Student fees updated (marked as "Paid")
3. Receipt generated (PDF)
4. Receipt printed (if selected)
5. Email sent to parent with receipt attachment
6. Notification shown to user: "✓ Payment recorded successfully"

---

**6.6.2 Record Bank Transfer Payment**
```
┌────────────────────────────────────────────────────────┐
│ RECORD PAYMENT - BANK TRANSFER                        │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Student:       Mary Tanyi (SEC/2024/046) - Form 3A    │
│                Outstanding: 121,000 XAF                │
│                                                        │
│ Payment Details:                                       │
│ ──────────────────────────────────────────────        │
│ Amount Paid:   [50,000___] XAF  *                     │
│                (Partial payment)                       │
│                                                        │
│ Payment Date:  [12/10/2024] 📅  *                     │
│                                                        │
│ Payment Method: Bank Transfer ✓                       │
│                                                        │
│ Bank Details:                                          │
│ ──────────────────────────────────────────────        │
│ Bank Name:     [UBA Cameroon ▼]                       │
│ Transaction Ref:[UBA/2024/XYZ123_________]  *         │
│                                                        │
│ Proof of Payment:                                      │
│ [Upload Receipt Image/PDF] 📁  *                      │
│ (Max 5MB)                                              │
│                                                        │
│ Verification Status:                                   │
│ ⚪ Pending Verification                                │
│ ⚪ Verified (Payment confirmed)                        │
│ ⚪ Rejected (Invalid/Fraudulent)                       │
│                                                        │
│ ALLOCATION:                                            │
│ ──────────────────────────────────────────────        │
│ ☑ Tuition Fee: 50,000 XAF (Full)                      │
│ ☐ Boarding Fee: 0 XAF (Unpaid - 60,000 remaining)     │
│ ☐ Exam Fee: 0 XAF (Unpaid - 5,000 remaining)          │
│                                                        │
│ Remaining Balance After Payment: 71,000 XAF            │
│                                                        │
│ [CANCEL]  [RECORD PAYMENT]                            │
└────────────────────────────────────────────────────────┘
```

**Bank Transfer Workflow:**
1. Parent makes bank transfer
2. Parent uploads proof to school (or school receives it)
3. Bursar records payment (status: Pending)
4. Bursar checks bank account to confirm transfer
5. Bursar changes status to "Verified"
6. System updates student fees
7. Receipt sent to parent

---

**6.6.3 Sync Payment from EduTrustPay**

When parent pays via EduTrustPay online:

```
┌────────────────────────────────────────────────────────┐
│ 🔄 SYNCING PAYMENT FROM EDUTRUSTPAY...                │
├────────────────────────────────────────────────────────┤
│                                                        │
│ EduTrustPay Payment ID: PAY-2024-001234                │
│                                                        │
│ Payment Details:                                       │
│ ──────────────────────────────────────────────        │
│ Student:       John Doe (SEC/2024/045)                │
│ Amount:        61,000 XAF                             │
│ Method:        MTN Mobile Money                        │
│ Date:          15/10/2024 14:32                       │
│ Status:        Verified ✓                             │
│                                                        │
│ Payer:         Jane Doe                                │
│ Phone:         +237 670 123 456                        │
│ Email:         jane.doe@example.com                    │
│                                                        │
│ ██████████████████████████████  100%                   │
│                                                        │
│ ✓ Payment synced successfully                         │
│ ✓ Student fees updated                                │
│ ✓ Receipt available in EduTrustPay                    │
│                                                        │
│ [VIEW PAYMENT DETAILS]  [CLOSE]                       │
└────────────────────────────────────────────────────────┘
```

**EduTrustPay Sync Process:**
1. Parent pays via EduTrustPay (online)
2. EduTrustPay sends webhook to ScholarTrack
3. ScholarTrack receives payment notification
4. System validates payment (amount, student ID, etc.)
5. Payment record created with source: "EduTrustPay"
6. Student fees updated automatically
7. Sync log created (for audit trail)

**If Sync Fails:**
- System flags payment for manual review
- Admin can manually import payment from EduTrustPay
- Conflict resolution if student has offline payment for same fees

---

### 6.7 Fee Clearance & Enforcement

**6.7.1 Fee Clearance Status Check**

System automatically checks fee clearance before:
- Releasing report cards
- Issuing transcripts
- Processing transfers
- Allowing exam sitting (if policy enabled)

```
┌────────────────────────────────────────────────────────┐
│ FEE CLEARANCE CHECK: John Doe (SEC/2024/045)          │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Action Requested: Release Term 1 Report Card           │
│                                                        │
│ FEE STATUS:                                            │
│ ──────────────────────────────────────────────        │
│ Total Fees (Term 1):  61,000 XAF                      │
│ Total Paid:           61,000 XAF ✓                    │
│ Outstanding Balance:   0 XAF                           │
│                                                        │
│ ✅ FEE CLEARANCE: GRANTED                             │
│                                                        │
│ Report card can be released to student/parent.         │
│                                                        │
│ [RELEASE REPORT CARD]  [CANCEL]                       │
└────────────────────────────────────────────────────────┘
```

**Fee NOT Cleared:**
```
┌────────────────────────────────────────────────────────┐
│ ⚠️ FEE CLEARANCE CHECK: Mary Tanyi (SEC/2024/046)     │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Action Requested: Release Term 1 Report Card           │
│                                                        │
│ FEE STATUS:                                            │
│ ──────────────────────────────────────────────        │
│ Total Fees (Term 1):  121,000 XAF                     │
│ Total Paid:            50,000 XAF                     │
│ Outstanding Balance:   71,000 XAF ⚠️                  │
│                                                        │
│ ❌ FEE CLEARANCE: DENIED                              │
│                                                        │
│ Student has outstanding fees. Report card cannot be    │
│ released until fees are cleared.                       │
│                                                        │
│ School Policy: Students must clear at least 80% of    │
│ fees to collect report cards.                          │
│                                                        │
│ Options:                                               │
│ ⚪ Wait for payment                                    │
│ ⚪ Grant exception (requires Principal approval)       │
│ ⚪ Contact parent for payment                          │
│                                                        │
│ [CONTACT PARENT]  [REQUEST EXCEPTION]  [CANCEL]       │
└────────────────────────────────────────────────────────┘
```

**6.7.2 Fee Clearance Certificate**

When student needs proof of cleared fees (e.g., for transfer):

```
┌────────────────────────────────────────────────────────┐
│ GENERATE FEE CLEARANCE CERTIFICATE                     │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Student:       John Doe (SEC/2024/045)                │
│ Class:         Form 3A                                 │
│ Session:       2024/2025                               │
│                                                        │
│ FEE STATUS:                                            │
│ ──────────────────────────────────────────────        │
│ Term 1: Paid ✓ (61,000 XAF)                           │
│ Term 2: Paid ✓ (61,000 XAF)                           │
│ Term 3: Paid ✓ (61,000 XAF)                           │
│                                                        │
│ Total Paid: 183,000 XAF                                │
│ Outstanding: 0 XAF                                     │
│                                                        │
│ ✅ ALL FEES CLEARED                                    │
│                                                        │
│ Certificate will include:                              │
│ • Student details                                      │
│ • Fee breakdown by term                                │
│ • Total paid amount                                    │
│ • Clearance confirmation                               │
│ • Principal's signature                                │
│ • School stamp                                         │
│                                                        │
│ [CANCEL]  [GENERATE CERTIFICATE (PDF)]                │
└────────────────────────────────────────────────────────┘
```

**Sample Fee Clearance Certificate:**
```
═══════════════════════════════════════════════════════
                FEE CLEARANCE CERTIFICATE
═══════════════════════════════════════════════════════

St. Joseph's Secondary School, Bamenda
P.O. Box 123, Northwest Region, Cameroon

───────────────────────────────────────────────────────

This is to certify that:

Student Name:     JOHN DOE
Student ID:       SEC/2024/045
Class:            Form 3A
Academic Year:    2024/2025

Has cleared all school fees for the above academic year.

FEE SUMMARY:
───────────────────────────────────────────────────────
Term             Fees Assigned    Amount Paid    Balance
───────────────────────────────────────────────────────
First Term       61,000 XAF      61,000 XAF     0 XAF ✓
Second Term      61,000 XAF      61,000 XAF     0 XAF ✓
Third Term       61,000 XAF      61,000 XAF     0 XAF ✓
───────────────────────────────────────────────────────
TOTAL           183,000 XAF     183,000 XAF     0 XAF
───────────────────────────────────────────────────────

✅ FEE STATUS: FULLY CLEARED

This certificate is issued for official purposes.

Date: 30/06/2025

_______________________          _______________________
Bursar's Signature               Principal's Signature

[SCHOOL STAMP]

═══════════════════════════════════════════════════════
Certificate No: FCC/2024/00045
Generated on: 30/06/2025 at 10:45 AM
═══════════════════════════════════════════════════════
```

---

### 6.8 Fee Reports & Analytics

**6.8.1 Fee Collection Summary (Dashboard Widget)**
```
┌────────────────────────────────────────────────────────┐
│ 💰 FEE COLLECTION SUMMARY - FIRST TERM 2024/2025      │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Total Expected:    72,579,000 XAF                     │
│ Total Collected:   58,063,000 XAF                     │
│ Outstanding:       14,516,000 XAF                     │
│                                                        │
│ ████████████████████░░░░░░  80% Collection Rate       │
│                                                        │
│ BREAKDOWN BY STATUS:                                   │
│ • Fully Paid:      789 students (66%)                 │
│ • Partially Paid:  312 students (26%)                 │
│ • Unpaid:           88 students (8%)                  │
│                                                        │
│ TOP DEFAULTERS (Highest Outstanding):                 │
│ 1. Peter Nkeng (SEC/2024/089) - 121,000 XAF          │
│ 2. Grace Ayuk (SEC/2024/103) - 115,000 XAF           │
│ 3. Paul Fon (SEC/2024/156) - 110,000 XAF             │
│                                                        │
│ [VIEW FULL REPORT]  [SEND REMINDERS TO DEFAULTERS]    │
└────────────────────────────────────────────────────────┘
```

**6.8.2 Detailed Fee Collection Report**
```
FEE COLLECTION REPORT
Session: 2024/2025 | Term: First Term
Generated: 15/11/2024 at 14:30

═══════════════════════════════════════════════════════

OVERALL SUMMARY
───────────────────────────────────────────────────────
Total Students:         1,189
Total Fees Assigned:    72,579,000 XAF
Total Collected:        58,063,000 XAF
Total Outstanding:      14,516,000 XAF
Collection Rate:        80%

BREAKDOWN BY CLASS
───────────────────────────────────────────────────────
Class          Students  Expected      Collected    Rate
───────────────────────────────────────────────────────
Form 1A        48        2,688,000     2,419,200    90%
Form 1B        52        2,912,000     2,562,560    88%
Form 2A        45        2,610,000     2,349,000    90%
Form 3A        43        2,623,000     2,097,000    80%
Form 3B        41        2,501,000     1,875,750    75%
Form 4 Sci A   28        1,960,000     1,568,000    80%
Form 4 Sci B   25        1,750,000     1,225,000    70%
Form 4 Arts    30        2,100,000     1,680,000    80%
Form 5 Sci     35        2,625,000     2,100,000    80%
Lower 6 Sci    18        1,530,000     1,377,000    90%
Upper 6 Sci    15        1,350,000     1,215,000    90%
───────────────────────────────────────────────────────

BREAKDOWN BY FEE CATEGORY
───────────────────────────────────────────────────────
Category         Assigned      Collected    Outstanding
───────────────────────────────────────────────────────
Tuition Fee      55,000,000    46,200,000   8,800,000
Boarding Fee     12,000,000    9,600,000    2,400,000
Exam Fee         2,500,000     2,000,000    500,000
Library & ICT    1,800,000     1,440,000    360,000
Sports           1,200,000     960,000      240,000
PTA Levy         700,000       560,000      140,000
───────────────────────────────────────────────────────

PAYMENT METHODS USED
───────────────────────────────────────────────────────
Method           Transactions  Amount        %
───────────────────────────────────────────────────────
Cash             423           18,500,000    32%
Bank Transfer    256           15,000,000    26%
MTN MoMo         189           12,000,000    21%
Orange Money     78            5,500,000     9%
EduTrustPay      145           7,063,000     12%
───────────────────────────────────────────────────────

OUTSTANDING FEES BY URGENCY
───────────────────────────────────────────────────────
Overdue (>30 days):      5,200,000 XAF (178 students)
Due Soon (0-30 days):    9,316,000 XAF (222 students)
───────────────────────────────────────────────────────

═══════════════════════════════════════════════════════

[EXPORT TO EXCEL]  [EXPORT TO PDF]  [PRINT]
```

---

This continues in the next section. Shall I proceed with Section 7 (Exam & Grading Module)?

---

## 7. EXAM & GRADING MODULE

### 7.1 Module Overview

The Exam & Grading module manages the complete lifecycle of academic assessment from marks entry through report card generation. It handles internal exams (sequences) and external exam results (GCE O-Level, A-Level).

**Key Features:**
- Subject teacher marks entry with validation
- Multi-stage approval workflow (Draft → Submitted → Approved → Published)
- Automatic term average calculation with configurable weights
- Class rank and subject position calculation
- Report card generation (print-ready PDFs)
- GCE external exam results import
- Grade conversion (marks to letter grades)
- Historical performance tracking

**Academic Structure Reminder:**
- **First Cycle:** Forms 1-5 (O-Level preparation)
  - Forms 1-3: No streams, all students take same subjects
  - Forms 4-5: Streams begin (Science, Arts, Commercial)
- **Second Cycle:** Lower Sixth, Upper Sixth (A-Level)
  - All have streams (Science, Arts, Commercial)

---

### 7.2 Marks Entry Interface

**7.2.1 Subject Teacher Marks Entry Dashboard**

When a teacher logs in, they see all their assigned subjects:

```
┌────────────────────────────────────────────────────────────────┐
│ 📝 MARKS ENTRY DASHBOARD - Mr. Tabe (Mathematics)             │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ Session: 2024/2025 | Term: First Term | Sequence: Sequence 1  │
│                                                                │
│ YOUR ASSIGNED CLASSES:                                         │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ Class          Subject      Students  Entered  Status    Action│
│ ────────────────────────────────────────────────────────────  │
│ Form 1A        Mathematics  48        0/48     Not Started [Enter Marks]│
│ Form 2A        Mathematics  45        45/45    Submitted ✓ [Edit/Submit]│
│ Form 3A        Mathematics  43        28/43    In Progress [Continue]│
│ Form 3B        Mathematics  41        41/41    Approved ✓  [View Only]│
│ Form 4 Sci A   Mathematics  28        0/28     Not Started [Enter Marks]│
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ UPCOMING DEADLINES:                                            │
│ • Sequence 1 marks entry closes: 15/11/2024 (3 days left) ⚠️  │
│                                                                │
│ QUICK STATS:                                                   │
│ • Total Students: 205                                          │
│ • Marks Entered: 114 (56%)                                     │
│ • Pending Entry: 91 (44%)                                      │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

---

**7.2.2 Enter Marks for a Class**

Teacher clicks "Enter Marks" for Form 3A:

```
┌────────────────────────────────────────────────────────────────┐
│ ENTER MARKS - FORM 3A MATHEMATICS                              │
│ Session: 2024/2025 | Term: First Term | Sequence: Sequence 1  │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ Exam Date: 04/11/2024 | Max Score: 20                         │
│ Marks Entry Deadline: 15/11/2024                               │
│                                                                │
│ Progress: 28/43 students (65%) ████████████░░░░░              │
│                                                                │
│ [SAVE DRAFT]  [SUBMIT FOR APPROVAL]  [EXPORT TO EXCEL]        │
│                                                                │
│ ────────────────────────────────────────────────────────────  │
│ #  Photo  Student Name      Student ID    Mark(/20)  Grade    │
│ ────────────────────────────────────────────────────────────  │
│ 1  [👤]   Ayuk, Grace       SEC/2024/048  [15.5_]    B  ✓     │
│ 2  [👤]   Doe, John         SEC/2024/045  [14.0_]    C+ ✓     │
│ 3  [👤]   Fon, Peter        SEC/2024/052  [_____]    -        │
│ 4  [👤]   Mbah, Clara       SEC/2024/055  [17.5_]    A- ✓     │
│ 5  [👤]   Nkeng, Paul       SEC/2024/058  [12.0_]    C  ✓     │
│ 6  [👤]   Tanyi, Mary       SEC/2024/046  [18.0_]    A  ✓     │
│ 7  [👤]   Tabe, Samuel      SEC/2024/061  [_____]    -        │
│ 8  [👤]   Ashu, Rita        SEC/2024/064  [16.0_]    B+ ✓     │
│ ... (35 more students)                                         │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ Showing 1-8 of 43 | [← Previous] [1] [2] [3] [4] [5] [Next →]│
│                                                                │
│ QUICK STATS:                                                   │
│ • Class Average: 14.8/20 (based on 28 entered marks)          │
│ • Highest: 18.0 (Tanyi, Mary)                                 │
│ • Lowest: 12.0 (Nkeng, Paul)                                  │
│ • Pass Rate: 100% (threshold: 10/20)                          │
│                                                                │
│ ⚠️ VALIDATION ALERTS:                                          │
│ • 15 students still missing marks                             │
│ • Cannot submit until all marks are entered                   │
│                                                                │
│ [SAVE DRAFT]  [SUBMIT FOR APPROVAL]                           │
└────────────────────────────────────────────────────────────────┘
```

**Mark Entry Features:**
- **Inline editing:** Click on mark field, type, auto-saves
- **Grade auto-calculation:** As soon as mark is entered, grade appears
- **Validation:** 
  - Mark must be 0-20
  - No decimals beyond one place (15.5 allowed, 15.55 not allowed)
  - Warnings for unusual marks (e.g., 0, 20)
- **Bulk actions:**
  - "Mark all absent" (sets 0 for multiple students)
  - "Copy from previous sequence" (if student had same subject)
- **Save draft:** Saves progress, can continue later
- **Submit for approval:** Locks marks, sends to admin for review

---

**7.2.3 Grade Scale (0-20 System)**

```
GRADE CONVERSION TABLE
────────────────────────────────────────────────
Mark Range    Letter Grade    Performance Level
────────────────────────────────────────────────
18.0 - 20.0   A+              Exceptional
16.0 - 17.9   A               Excellent
15.0 - 15.9   A-              Very Good
14.0 - 14.9   B+              Good
13.0 - 13.9   B               Above Average
12.0 - 12.9   B-              Satisfactory
11.0 - 11.9   C+              Fair
10.0 - 10.9   C               Pass
9.0 - 9.9     D+              Below Average
8.0 - 8.9     D               Poor
0.0 - 7.9     F               Fail
────────────────────────────────────────────────
Pass Mark: 10.0/20 (50%)
```

This grade scale is configurable in System Settings.

---

**7.2.4 Marks Entry - Bulk Upload (Excel)**

For teachers with many students, bulk upload is faster:

```
┌────────────────────────────────────────────────────────┐
│ BULK UPLOAD MARKS - FORM 3A MATHEMATICS                │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Step 1: Download Template                              │
│ ─────────────────────────────────────────────         │
│ [📥 DOWNLOAD EXCEL TEMPLATE]                          │
│                                                        │
│ The template includes:                                 │
│ • Student ID, Name (pre-filled)                        │
│ • Empty "Mark" column for you to fill                 │
│                                                        │
│ ────────────────────────────────────────────          │
│                                                        │
│ Step 2: Fill Marks in Excel                            │
│ ─────────────────────────────────────────────         │
│ Open the template and enter marks (0-20)              │
│                                                        │
│ ────────────────────────────────────────────          │
│                                                        │
│ Step 3: Upload Filled Template                         │
│ ─────────────────────────────────────────────         │
│ [Choose File] 📁   No file selected                   │
│                                                        │
│ [CANCEL]  [UPLOAD & VALIDATE]                         │
└────────────────────────────────────────────────────────┘
```

**Excel Template Format:**
```
| Student ID   | Student Name  | Mark (/20) | Grade (Auto) |
|--------------|---------------|------------|--------------|
| SEC/2024/048 | Ayuk, Grace   | 15.5       | B            |
| SEC/2024/045 | Doe, John     | 14.0       | C+           |
| SEC/2024/052 | Fon, Peter    | 13.5       | B            |
| ...          |               |            |              |
```

**After Upload:**
- System validates all marks (range 0-20, correct format)
- Shows validation errors if any
- Teacher reviews before final submit
- Marks imported into system

---

### 7.3 Marks Approval Workflow

**Workflow States:**
1. **Draft** - Teacher is entering marks, can edit freely
2. **Submitted** - Teacher submits, marks locked, awaiting admin review
3. **Approved** - Admin/Principal approves, marks finalized
4. **Published** - Marks visible to students/parents, report cards can be generated

**7.3.1 Teacher Submits Marks**

```
┌────────────────────────────────────────────────────────┐
│ SUBMIT MARKS FOR APPROVAL                              │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Class:    Form 3A Mathematics                          │
│ Term:     First Term, Sequence 1                       │
│ Students: 43                                           │
│                                                        │
│ VALIDATION CHECK:                                      │
│ ✓ All 43 students have marks                          │
│ ✓ All marks are within valid range (0-20)             │
│ ✓ No duplicate entries                                │
│                                                        │
│ STATISTICS:                                            │
│ • Class Average: 14.8/20                              │
│ • Highest Mark: 18.0 (Tanyi, Mary)                    │
│ • Lowest Mark: 12.0 (Nkeng, Paul)                     │
│ • Pass Rate: 100% (43/43 students ≥10)                │
│                                                        │
│ ⚠️ WARNING:                                            │
│ Once submitted, you cannot edit marks unless admin     │
│ rejects them and sends back for revision.              │
│                                                        │
│ Are you sure all marks are correct?                    │
│                                                        │
│ [CANCEL]  [YES, SUBMIT FOR APPROVAL]                  │
└────────────────────────────────────────────────────────┘
```

**After Submission:**
- Marks status changes to "Submitted"
- Teacher can no longer edit
- Notification sent to admin/Vice Principal (Academic)
- Marks appear in admin's approval queue

---

**7.3.2 Admin Reviews & Approves Marks**

Admin dashboard shows pending approvals:

```
┌────────────────────────────────────────────────────────────────┐
│ 📋 MARKS APPROVAL QUEUE - Vice Principal (Academic)           │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ Session: 2024/2025 | Term: First Term | Sequence: Sequence 1  │
│                                                                │
│ PENDING APPROVAL: 12 submissions                               │
│                                                                │
│ Class       Subject      Teacher    Students  Submitted   Action│
│ ────────────────────────────────────────────────────────────  │
│ Form 1A     Mathematics  Mr. Tabe   48        12/11/24  [Review]│
│ Form 2A     Mathematics  Mr. Tabe   45        12/11/24  [Review]│
│ Form 3A     Mathematics  Mr. Tabe   43        13/11/24  [Review]│
│ Form 1A     English      Mrs. Nkeng 48        12/11/24  [Review]│
│ Form 2A     Physics      Mr. Fon    45        13/11/24  [Review]│
│ ... (7 more)                                                   │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ [APPROVE ALL]  [EXPORT SUMMARY]                                │
└────────────────────────────────────────────────────────────────┘
```

**Admin Reviews Individual Submission:**

```
┌────────────────────────────────────────────────────────────────┐
│ REVIEW MARKS - FORM 3A MATHEMATICS                             │
│ Teacher: Mr. Tabe | Submitted: 13/11/2024 at 10:45            │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ SUMMARY STATISTICS:                                            │
│ • Total Students: 43                                           │
│ • Class Average: 14.8/20 (74%)                                │
│ • Highest: 18.0 | Lowest: 12.0 | Pass Rate: 100%              │
│                                                                │
│ GRADE DISTRIBUTION:                                            │
│ A+/A/A-: 8 students (19%)  ████░░░░░░░░░░░                   │
│ B+/B/B-: 22 students (51%) ██████████░░░░░                   │
│ C+/C:    13 students (30%) ██████░░░░░░░░░                   │
│ D+/D/F:  0 students (0%)   ░░░░░░░░░░░░░░░                   │
│                                                                │
│ ⚠️ AUTOMATIC CHECKS:                                           │
│ ✓ No marks outside range (0-20)                               │
│ ✓ No duplicate entries                                        │
│ ✓ All students accounted for                                  │
│ ⚠️ Outliers detected:                                          │
│   - 3 students scored 18+ (top 7%) - verify if correct        │
│   - No students below 10 - unusually high performance          │
│                                                                │
│ VIEW MARKS: [All Students] [Top 5] [Bottom 5] [Flagged]       │
│                                                                │
│ ────────────────────────────────────────────────────────────  │
│ FULL MARKS LIST (Sample):                                      │
│ ────────────────────────────────────────────────────────────  │
│ #  Student Name      ID           Mark    Grade   Flag        │
│ ────────────────────────────────────────────────────────────  │
│ 1  Tanyi, Mary       SEC/2024/046 18.0    A       ⚠️ High     │
│ 2  Mbah, Clara       SEC/2024/055 17.5    A-      ⚠️ High     │
│ 3  Ashu, Rita        SEC/2024/064 16.0    B+                  │
│ 4  Ayuk, Grace       SEC/2024/048 15.5    B                   │
│ 5  Doe, John         SEC/2024/045 14.0    C+                  │
│ ... (38 more)                                                  │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ DECISION:                                                      │
│ ⚪ APPROVE - Marks are correct, finalize them                  │
│ ⚪ REJECT & RETURN - Send back to teacher for revision         │
│                                                                │
│ Comments for Teacher (if rejecting):                           │
│ ┌──────────────────────────────────────────────────────────┐ │
│ │ e.g., "Please verify marks for top 3 students.          │ │
│ │ Scores seem unusually high."                             │ │
│ └──────────────────────────────────────────────────────────┘ │
│                                                                │
│ [CANCEL]  [REJECT & RETURN]  [APPROVE MARKS]                  │
└────────────────────────────────────────────────────────────────┘
```

**If Admin Approves:**
- Marks status → "Approved"
- Marks are now finalized
- Teacher notified of approval
- Marks included in term average calculation

**If Admin Rejects:**
- Marks status → "Returned for Revision"
- Teacher can edit again
- Teacher must resubmit after corrections

---

**7.3.3 Publish Marks to Students/Parents**

Once all subjects for a sequence are approved, admin can publish:

```
┌────────────────────────────────────────────────────────┐
│ PUBLISH MARKS - FIRST TERM, SEQUENCE 1                │
├────────────────────────────────────────────────────────┤
│                                                        │
│ APPROVAL STATUS:                                       │
│ ──────────────────────────────────────────────        │
│ Total Subjects: 85 (across all classes)               │
│ Approved: 78 (92%)                                     │
│ Pending: 7 (8%)                                        │
│                                                        │
│ ⚠️ 7 subjects still pending approval:                  │
│ • Form 4 Arts - History (Mrs. Tanyi)                  │
│ • Form 5 Science - Biology (Mr. Njie)                 │
│ • Lower Sixth - Economics (Mrs. Fomba)                │
│ ... (4 more)                                           │
│                                                        │
│ ────────────────────────────────────────────          │
│                                                        │
│ Do you want to publish approved marks now?             │
│                                                        │
│ ⚪ Publish only approved subjects (78)                 │
│    Students will see marks for approved subjects       │
│    Pending subjects will show "Not Published"          │
│                                                        │
│ ⚪ Wait until all subjects are approved                │
│    Recommended if close to deadline                    │
│                                                        │
│ When published:                                        │
│ • Marks visible in Student Portal                     │
│ • Marks visible in Parent Portal                      │
│ • Email/SMS notifications sent                        │
│ • Sequence averages calculated (where complete)       │
│                                                        │
│ [CANCEL]  [PUBLISH APPROVED MARKS]                    │
└────────────────────────────────────────────────────────┘
```

**After Publishing:**
- Students can see their marks in Student Portal
- Parents can see marks in Parent Portal
- System sends notifications:
  ```
  📧 Email to parents:
  Subject: Sequence 1 Marks Published - John Doe
  
  Dear Mrs. Jane Doe,
  
  Marks for Sequence 1 (First Term, 2024/2025) have been 
  published for your child John Doe (SEC/2024/045).
  
  You can view the marks by logging into the Parent Portal:
  https://school.example.com/parent
  
  Regards,
  St. Joseph's Secondary School
  ```

---

### 7.4 Term Average Calculation

**7.4.1 Automatic Calculation**

After marks for all sequences in a term are published:

**Example: First Term with 2 Sequences**

```
STUDENT: John Doe (SEC/2024/045)
CLASS: Form 3A
TERM: First Term 2024/2025

SUBJECT-BY-SUBJECT BREAKDOWN:
────────────────────────────────────────────────────────────
Subject          Seq 1  Seq 2  Term Avg   Coeff  Weighted
                 (50%)  (50%)  (Mean)                     
────────────────────────────────────────────────────────────
Mathematics      14.0   15.0   14.5       1.0    14.5
English          13.5   14.5   14.0       1.0    14.0
Physics          15.0   14.0   14.5       1.0    14.5
Chemistry        14.5   15.5   15.0       1.0    15.0
Biology          13.0   14.0   13.5       1.0    13.5
Literature       12.0   13.0   12.5       1.0    12.5
History          14.0   14.5   14.25      1.0    14.25
Geography        13.5   14.0   13.75      1.0    13.75
French           12.5   13.5   13.0       1.0    13.0
Religious St.    15.0   16.0   15.5       0.5    7.75
Physical Ed.     14.0   15.0   14.5       0.5    7.25
────────────────────────────────────────────────────────────
TOTALS:                                    10.0   143.0

TERM AVERAGE = Total Weighted / Total Coefficient
             = 143.0 / 10.0
             = 14.3/20

OVERALL GRADE: C+
CLASS RANK: 8/43
```

**Formula:**
```
For each subject:
  Term Average = (Seq1 × Weight1) + (Seq2 × Weight2) + ...
  Weighted Score = Term Average × Coefficient

Overall Term Average = Sum(Weighted Scores) / Sum(Coefficients)
```

---

**7.4.2 Handling Missing Sequences**

If a student was absent for an exam:

```
SCENARIO: Mary was absent for Sequence 1 Mathematics

OPTION A: Zero for Absent (Default)
────────────────────────────────────
Mathematics   Seq 1: 0    Seq 2: 15.0   Term Avg: 7.5
(This drastically lowers term average - harsh)

OPTION B: Exclude Missing Sequence (Configurable)
────────────────────────────────────
Mathematics   Seq 1: -    Seq 2: 15.0   Term Avg: 15.0
(Uses only Sequence 2, more lenient)

OPTION C: Make-up Exam (Recommended)
────────────────────────────────────
Teacher schedules make-up exam for Mary
Mary takes exam late, gets 14.0
Mathematics   Seq 1: 14.0  Seq 2: 15.0   Term Avg: 14.5
```

System setting controls which option is used. Most schools prefer **Option C** (make-up exams).

---

### 7.5 Class Rank Calculation

**7.5.1 Overall Class Rank**

After all term averages calculated:

```
FORM 3A - FIRST TERM 2024/2025 RANKING
────────────────────────────────────────────────────────────
Rank  Student Name      ID           Term Avg  Grade  Status
────────────────────────────────────────────────────────────
1     Tanyi, Mary       SEC/2024/046 17.2      A      🥇
2     Mbah, Clara       SEC/2024/055 16.8      A      🥈
3     Ashu, Rita        SEC/2024/064 16.1      B+     🥉
4     Ayuk, Grace       SEC/2024/048 15.7      A-
5     Fon, Emmanuel     SEC/2024/051 15.3      A-
6     Njie, Paul        SEC/2024/057 15.0      A-
7     Tabe, Samuel      SEC/2024/061 14.8      B+
8     Doe, John         SEC/2024/045 14.3      C+     ←
9     Nkeng, Peter      SEC/2024/058 14.1      C+
10    Akum, Grace       SEC/2024/062 13.9      B
...
43    Tikum, Samuel     SEC/2024/089 10.2      C
────────────────────────────────────────────────────────────
Class Average: 14.2/20
Pass Rate: 100% (43/43 students)
```

**Tie-Breaking Rules:**
If two students have same average (e.g., both 14.5):
1. Compare total marks across all subjects (higher wins)
2. If still tied, compare performance in core subjects (Math, English, Science)
3. If still tied, assign same rank with notation (e.g., "8= " for tied 8th)

---

**7.5.2 Subject Position**

Students also get ranked per subject:

```
MATHEMATICS - FORM 3A - SEQUENCE 1
────────────────────────────────────────────────────────────
Pos   Student Name      Mark    Grade
────────────────────────────────────────────────────────────
1     Tanyi, Mary       18.0    A       🥇
2     Mbah, Clara       17.5    A-      🥈
3     Ashu, Rita        16.0    B+      🥉
4     Ayuk, Grace       15.5    B
5     Fon, Emmanuel     15.0    A-
...
15    Doe, John         14.0    C+      ← Your Position
...
────────────────────────────────────────────────────────────
Class Average: 14.8/20
```

This appears on report cards: "15th/43 in Mathematics"

---

### 7.6 Report Card Generation

**7.6.1 Report Card Template (Cameroon Standard)**

```
═══════════════════════════════════════════════════════════
            ST. JOSEPH'S SECONDARY SCHOOL
               P.O. Box 123, Bamenda
           Northwest Region, Cameroon
           
               📞 +237 233 XX XX XX
           📧 info@stjosephsbamenda.cm
═══════════════════════════════════════════════════════════

                   REPORT CARD
        Academic Year 2024/2025 - FIRST TERM

───────────────────────────────────────────────────────────
STUDENT INFORMATION
───────────────────────────────────────────────────────────
Name:           JOHN DOE
Student ID:     SEC/2024/045
Class:          FORM 3A
Date of Birth:  15/03/2011 (Age 13)
Residence:      Day Student

───────────────────────────────────────────────────────────
ACADEMIC PERFORMANCE
───────────────────────────────────────────────────────────

Subject            Coeff  Seq1  Seq2  Term  Grade  Pos   Teacher
                          /20   /20   Avg
───────────────────────────────────────────────────────────
Mathematics        1.0    14.0  15.0  14.5  C+    15/43  Mr. Tabe
English Language   1.0    13.5  14.5  14.0  C+    18/43  Mrs. Nkeng
Physics            1.0    15.0  14.0  14.5  C+    12/43  Mr. Fon
Chemistry          1.0    14.5  15.5  15.0  A-    10/43  Mrs. Ayuk
Biology            1.0    13.0  14.0  13.5  B     20/43  Mr. Njie
Literature in Eng. 1.0    12.0  13.0  12.5  B-    25/43  Mr. Ashu
History            1.0    14.0  14.5  14.25 C+    16/43  Mrs. Tanyi
Geography          1.0    13.5  14.0  13.75 B     19/43  Mr. Tikum
French             1.0    12.5  13.5  13.0  B     22/43  Mrs. Akum
Religious Studies  0.5    15.0  16.0  15.5  A-    5/43   Fr. Mbah
Physical Education 0.5    14.0  15.0  14.5  C+    18/43  Coach Fomba
───────────────────────────────────────────────────────────
TOTAL COEFFICIENT: 10.0
TOTAL WEIGHTED:    143.0

TERM AVERAGE:      14.3/20 (71.5%)
OVERALL GRADE:     C+
CLASS RANK:        8/43 students
CLASS AVERAGE:     14.2/20

───────────────────────────────────────────────────────────
ATTENDANCE
───────────────────────────────────────────────────────────
Days Present:      45/48 (94%)
Days Absent:       3
Punctuality:       Good

───────────────────────────────────────────────────────────
CONDUCT & DISCIPLINE
───────────────────────────────────────────────────────────
Behavior:          Good
Disciplinary Notes: None

───────────────────────────────────────────────────────────
CLASS TEACHER'S REMARKS
───────────────────────────────────────────────────────────
John is a hardworking student who shows consistent effort 
in class. His performance in Chemistry is commendable. He 
should focus more on Mathematics and Literature to improve 
his overall standing. With dedication, he can achieve 
better results next term. Keep it up!

Class Teacher: Mrs. Ayuk              Date: 18/12/2024
               _________________      Signature: __________

───────────────────────────────────────────────────────────
PRINCIPAL'S REMARKS
───────────────────────────────────────────────────────────
Satisfactory performance. Continue working hard.

Principal: Rev. Fr. Mbah               Date: 20/12/2024
          _________________            Signature: __________

───────────────────────────────────────────────────────────
NEXT TERM INFORMATION
───────────────────────────────────────────────────────────
Second Term begins:  06/01/2025
Fees for Second Term: 61,000 XAF
Fee Payment Due:      15/01/2025

───────────────────────────────────────────────────────────
PARENT/GUARDIAN ACKNOWLEDGEMENT
───────────────────────────────────────────────────────────
I have reviewed this report card.

Parent Name: ____________________  Date: _______________

Parent Signature: ____________________

───────────────────────────────────────────────────────────
                  [SCHOOL STAMP]

           ⚠️ This report card is only valid with 
               fee clearance certificate
═══════════════════════════════════════════════════════════
Report Card ID: RC/2024/045/T1
Generated: 20/12/2024 at 14:30
```

---

**7.6.2 Generate Report Cards (Bulk)**

```
┌────────────────────────────────────────────────────────┐
│ GENERATE REPORT CARDS                                  │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Session:  [2024/2025 ▼]                               │
│ Term:     [First Term ▼]                              │
│                                                        │
│ SELECT STUDENTS:                                       │
│ ──────────────────────────────────────────────        │
│ ⚪ All students in a class                            │
│    Class: [Form 3A ▼] (43 students)                   │
│                                                        │
│ ⚪ Individual student                                  │
│    Student: [Search ▼________________]                │
│                                                        │
│ ⚪ All students in school (1,189 students)             │
│                                                        │
│ ────────────────────────────────────────────          │
│                                                        │
│ REPORT CARD OPTIONS:                                   │
│ ──────────────────────────────────────────────        │
│ Template: [Standard Cameroon ▼]                       │
│           (Can customize in Settings)                  │
│                                                        │
│ Include:                                               │
│ ☑ Sequence marks (Seq 1, Seq 2)                      │
│ ☑ Term average & grade                                │
│ ☑ Class rank & subject positions                      │
│ ☑ Attendance summary                                   │
│ ☑ Teacher remarks                                      │
│ ☑ Principal remarks                                    │
│ ☑ Fee clearance status                                │
│ ☐ Student photo                                       │
│                                                        │
│ Format:   ⚪ PDF  ⚪ Print-ready PDF  ⚪ Excel          │
│                                                        │
│ ────────────────────────────────────────────          │
│                                                        │
│ ⚠️ FEE CLEARANCE CHECK:                                │
│ Only students with cleared fees can collect report    │
│ cards. System will flag students with outstanding fees│
│                                                        │
│ PREVIEW BEFORE GENERATING:                             │
│ 43 report cards will be generated                     │
│ 38 students: Fee cleared ✓                            │
│ 5 students: Outstanding fees ⚠️                        │
│                                                        │
│ [CANCEL]  [GENERATE REPORT CARDS]                     │
└────────────────────────────────────────────────────────┘
```

**Generation Progress:**
```
┌────────────────────────────────────────────────────────┐
│ 📄 GENERATING REPORT CARDS...                          │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Progress: 28/43 (65%)                                  │
│                                                        │
│ ████████████████████░░░░░░░░░░  65%                   │
│                                                        │
│ Current: Generating for Nkeng, Peter (SEC/2024/058)   │
│                                                        │
│ ✓ Calculated term averages                            │
│ ✓ Computed class ranks                                │
│ ✓ Generated PDF templates                             │
│ • Merging PDFs...                                      │
│                                                        │
│ Please wait...                                         │
│                                                        │
└────────────────────────────────────────────────────────┘
```

**After Generation:**
```
┌────────────────────────────────────────────────────────┐
│ ✅ REPORT CARDS GENERATED SUCCESSFULLY                 │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Total Generated: 43 report cards                       │
│                                                        │
│ FILES:                                                 │
│ 📁 Form_3A_Report_Cards_Term1_2024_2025.pdf           │
│    (All 43 report cards in one file, 86 pages)        │
│                                                        │
│ FEE CLEARANCE SUMMARY:                                 │
│ • 38 students: Can collect ✓                          │
│ • 5 students: Blocked (fees owing) ⚠️                  │
│                                                        │
│ Students with Outstanding Fees:                        │
│ 1. Fon, Peter (SEC/2024/052) - 25,000 XAF            │
│ 2. Tikum, Samuel (SEC/2024/089) - 61,000 XAF         │
│ 3. Ndi, Grace (SEC/2024/091) - 15,000 XAF            │
│ 4. Ayuk, Paul (SEC/2024/094) - 30,000 XAF            │
│ 5. Tabe, Rita (SEC/2024/098) - 12,000 XAF            │
│                                                        │
│ [DOWNLOAD PDF]  [PRINT ALL]  [EMAIL TO PARENTS]       │
│ [MARK AS DISTRIBUTED]  [SEND FEE REMINDERS]           │
└────────────────────────────────────────────────────────┘
```

---

### 7.7 External Exam Results (GCE O-Level, A-Level)

**7.7.1 Import GCE Results**

For Form 5 (O-Level) and Upper Sixth (A-Level) students:

```
┌────────────────────────────────────────────────────────┐
│ IMPORT GCE EXAM RESULTS                                │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Exam Type:     ⚪ GCE O-Level  ⚪ GCE A-Level           │
│                                                        │
│ Session:       [2024/2025 ▼]                          │
│                                                        │
│ Exam Session:  ⚪ June 2025  ⚪ November 2024           │
│                                                        │
│ ────────────────────────────────────────────          │
│                                                        │
│ IMPORT METHOD:                                         │
│ ──────────────────────────────────────────────        │
│ ⚪ Manual Entry (Enter results one by one)             │
│ ⚪ Bulk Upload (Excel template)                        │
│ ⚪ Scan Results Sheet (OCR - if available)             │
│                                                        │
│ [CHOOSE METHOD]                                        │
│                                                        │
└────────────────────────────────────────────────────────┘
```

**Manual Entry Interface:**
```
┌────────────────────────────────────────────────────────┐
│ ENTER GCE O-LEVEL RESULTS: Mary Tanyi (SEC/2020/015)  │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Student: Mary Tanyi                                    │
│ Class:   Form 5 Science                                │
│ Exam:    GCE O-Level June 2025                        │
│                                                        │
│ SUBJECTS & GRADES:                                     │
│ ──────────────────────────────────────────────        │
│ Subject                    Grade (A-F)  Pass?          │
│ ────────────────────────────────────────────          │
│ Mathematics                [A____]      ✓ Pass        │
│ English Language           [B____]      ✓ Pass        │
│ Physics                    [A____]      ✓ Pass        │
│ Chemistry                  [B____]      ✓ Pass        │
│ Biology                    [A____]      ✓ Pass        │
│ French                     [C____]      ✓ Pass        │
│                                                        │
│ [+ ADD SUBJECT]                                        │
│                                                        │
│ ────────────────────────────────────────────          │
│                                                        │
│ SUMMARY:                                               │
│ Total Subjects: 6                                      │
│ Passes: 6 (100%)                                       │
│ Fails: 0                                               │
│ Distinctions (A): 3                                    │
│                                                        │
│ Overall Result: ✅ PASSED (5 credits including         │
│                    English and Mathematics)            │
│                                                        │
│ [CANCEL]  [SAVE RESULTS]                              │
└────────────────────────────────────────────────────────┘
```

**GCE Grade Scale:**
```
A = Distinction (80-100%)
B = Credit (70-79%)
C = Credit (60-69%)
D = Pass (50-59%)
E = Pass (45-49%)
F = Fail (0-44%)

O-Level Pass Requirement: 
Minimum 5 credits (A-C) including English and Mathematics

A-Level Pass Requirement:
Minimum 2 passes (A-E) in principal subjects
```

---

This is Section 7. Should I continue with Section 8 (Timetable Management)?

---

## 8. TIMETABLE MANAGEMENT MODULE

### 8.1 Module Overview

The Timetable Management module allows schools to create, manage, and publish class timetables. It handles teacher-subject-room assignments, prevents scheduling conflicts, tracks teacher workload, and supports timetable changes.

**Key Features:**
- Visual timetable builder (drag-and-drop or grid-based)
- Automatic conflict detection (teacher double-booking, room overlap)
- Teacher workload tracking (hours per week)
- Room utilization management
- Joint class support (e.g., Form 4 Science A+B together)
- Substitution management
- Timetable publishing to student/teacher portals

---

### 8.2 Pre-requisites Setup

Before creating timetables, configure:

**8.2.1 Time Slots Configuration**

```
┌────────────────────────────────────────────────────────┐
│ CONFIGURE TIME SLOTS                                   │
├────────────────────────────────────────────────────────┤
│                                                        │
│ School Day Structure:                                  │
│ ──────────────────────────────────────────────        │
│                                                        │
│ Period  Start Time  End Time   Duration   Type        │
│ ────────────────────────────────────────────          │
│ 1       07:30      08:15       45 min     Teaching    │
│ 2       08:15      09:00       45 min     Teaching    │
│ 3       09:00      09:45       45 min     Teaching    │
│         09:45      10:00       15 min     BREAK       │
│ 4       10:00      10:45       45 min     Teaching    │
│ 5       10:45      11:30       45 min     Teaching    │
│ 6       11:30      12:15       45 min     Teaching    │
│         12:15      13:00       45 min     LUNCH       │
│ 7       13:00      13:45       45 min     Teaching    │
│ 8       13:45      14:30       45 min     Teaching    │
│ 9       14:30      15:15       45 min     Teaching    │
│ ────────────────────────────────────────────          │
│                                                        │
│ Total Teaching Periods per Day: 9                      │
│ Total Teaching Time: 6 hours 45 minutes               │
│                                                        │
│ Days:  ☑ Monday  ☑ Tuesday  ☑ Wednesday               │
│        ☑ Thursday  ☑ Friday  ☐ Saturday               │
│                                                        │
│ [EDIT TIME SLOTS]  [SAVE CONFIGURATION]               │
└────────────────────────────────────────────────────────┘
```

**8.2.2 Rooms Configuration**

```
┌────────────────────────────────────────────────────────┐
│ MANAGE ROOMS                                           │
├────────────────────────────────────────────────────────┤
│                                                        │
│ CLASSROOMS                                             │
│ ────────────────────────────────────────────          │
│ Room      Type        Capacity   Facilities           │
│ ────────────────────────────────────────────          │
│ Room 1    Classroom   60         Projector, AC        │
│ Room 2    Classroom   60         Projector            │
│ Room 3    Classroom   60         -                    │
│ Lab 1     Science     40         Chemistry Lab        │
│ Lab 2     Science     40         Physics Lab          │
│ Lab 3     Science     40         Biology Lab          │
│ Comp Lab  ICT         30         30 Computers         │
│ Library   Library     100        WiFi                 │
│ Hall      Assembly    500        Stage, PA System     │
│ ────────────────────────────────────────────          │
│                                                        │
│ [+ ADD ROOM]  [EDIT]  [DELETE]                        │
└────────────────────────────────────────────────────────┘
```

---

### 8.3 Create Timetable for a Class

**8.3.1 Timetable Creation Wizard**

```
┌────────────────────────────────────────────────────────┐
│ CREATE TIMETABLE - STEP 1/3: SELECT CLASS             │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Session:       [2024/2025 ▼]  *                       │
│ Term:          [First Term ▼]  *                      │
│                                                        │
│ Class Section: [Form 3A ▼]  *                         │
│                                                        │
│ Class Details:                                         │
│ • Form: Form 3 (First Cycle, no streams)              │
│ • Students: 43                                         │
│ • Class Teacher: Mrs. Ayuk                            │
│ • Primary Room: Room 10                               │
│                                                        │
│ Subjects for this class (11 total):                   │
│ ✓ Mathematics, English, Physics, Chemistry,           │
│   Biology, Literature, History, Geography,            │
│   French, Religious Studies, Physical Education       │
│                                                        │
│ [CANCEL]  [NEXT: ASSIGN PERIODS →]                    │
└────────────────────────────────────────────────────────┘
```

**STEP 2: Assign Subjects to Periods**

```
┌────────────────────────────────────────────────────────────────┐
│ CREATE TIMETABLE - STEP 2/3: ASSIGN SUBJECTS TO PERIODS       │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ Class: Form 3A | Room: Room 10 (default)                      │
│                                                                │
│ WEEKLY TIMETABLE GRID (9 periods × 5 days = 45 slots)         │
│                                                                │
│        Monday    Tuesday   Wednesday Thursday  Friday         │
│ ────────────────────────────────────────────────────────────  │
│ P1     [Math  ]  [Eng   ]  [Phys  ]  [Chem  ]  [Bio   ]      │
│        Mr.Tabe   Mrs.Nkeng Mr.Fon    Mrs.Ayuk Mr.Njie         │
│        Room 10   Room 10   Lab 2     Lab 1    Lab 3           │
│                                                                │
│ P2     [Math  ]  [Eng   ]  [Phys  ]  [Chem  ]  [Bio   ]      │
│        Mr.Tabe   Mrs.Nkeng Mr.Fon    Mrs.Ayuk Mr.Njie         │
│        Room 10   Room 10   Lab 2     Lab 1    Lab 3           │
│                                                                │
│ P3     [Lit   ]  [Hist  ]  [Geo   ]  [Fre   ]  [Math  ]      │
│        Mr.Ashu   Mrs.Tanyi Mr.Tikum  Mrs.Akum Mr.Tabe         │
│        Room 10   Room 10   Room 10   Room 10  Room 10         │
│        ────────   BREAK (10:00-10:15)   ────────              │
│                                                                │
│ P4     [Lit   ]  [Hist  ]  [Geo   ]  [Fre   ]  [Math  ]      │
│        Mr.Ashu   Mrs.Tanyi Mr.Tikum  Mrs.Akum Mr.Tabe         │
│        Room 10   Room 10   Room 10   Room 10  Room 10         │
│                                                                │
│ P5     [Phys  ]  [Chem  ]  [Bio   ]  [Eng   ]  [Phys  ]      │
│        Mr.Fon    Mrs.Ayuk  Mr.Njie   Mrs.Nkeng Mr.Fon         │
│        Lab 2     Lab 1     Lab 3     Room 10  Lab 2           │
│                                                                │
│ P6     [Eng   ]  [Math  ]  [Lit   ]  [Hist  ]  [Geo   ]      │
│        Mrs.Nkeng Mr.Tabe   Mr.Ashu   Mrs.Tanyi Mr.Tikum       │
│        Room 10   Room 10   Room 10   Room 10  Room 10         │
│        ────────   LUNCH (12:15-13:00)    ────────             │
│                                                                │
│ P7     [Fre   ]  [RelSt ]  [PE    ]  [Math  ]  [Eng   ]      │
│        Mrs.Akum  Fr.Mbah   Coach     Mr.Tabe   Mrs.Nkeng     │
│        Room 10   Chapel    Field     Room 10  Room 10         │
│                                                                │
│ P8     [Free  ]  [Math  ]  [Eng   ]  [Lit   ]  [Free  ]      │
│        Study     Mr.Tabe   Mrs.Nkeng Mr.Ashu   Study          │
│        Library   Room 10   Room 10   Room 10  Library         │
│                                                                │
│ P9     [Free  ]  [Chem  ]  [Bio   ]  [Free  ]  [Free  ]      │
│        Study     Mrs.Ayuk  Mr.Njie   Study     Study          │
│        Library   Lab 1     Lab 3     Library  Library         │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ SUBJECT ALLOCATION SUMMARY:                                    │
│ • Mathematics: 6 periods/week (Mr. Tabe)                      │
│ • English: 5 periods/week (Mrs. Nkeng)                        │
│ • Physics: 4 periods/week (Mr. Fon)                           │
│ • Chemistry: 4 periods/week (Mrs. Ayuk)                       │
│ • Biology: 4 periods/week (Mr. Njie)                          │
│ • Literature: 3 periods/week (Mr. Ashu)                       │
│ • History: 2 periods/week (Mrs. Tanyi)                        │
│ • Geography: 2 periods/week (Mr. Tikum)                       │
│ • French: 2 periods/week (Mrs. Akum)                          │
│ • Religious Studies: 1 period/week (Fr. Mbah)                 │
│ • Physical Education: 1 period/week (Coach Fomba)             │
│ • Free Study: 11 periods/week (Library)                       │
│                                                                │
│ [← BACK]  [CANCEL]  [NEXT: REVIEW & SAVE →]                  │
└────────────────────────────────────────────────────────────────┘
```

**How to Assign:**
- Click on empty slot
- Select subject from dropdown
- Select teacher (automatically filtered by subject)
- Select room (defaults to class room, can change)
- System checks for conflicts (explained below)

---

**STEP 3: Review & Save**

```
┌────────────────────────────────────────────────────────┐
│ CREATE TIMETABLE - STEP 3/3: REVIEW & SAVE            │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Class: Form 3A                                         │
│ Session: 2024/2025 | Term: First Term                 │
│                                                        │
│ ✅ VALIDATION PASSED                                   │
│ ──────────────────────────────────────────────        │
│ ✓ All required subjects assigned                      │
│ ✓ No teacher conflicts detected                       │
│ ✓ No room conflicts detected                          │
│ ✓ Period distribution is balanced                     │
│                                                        │
│ TIMETABLE SUMMARY:                                     │
│ • Total Periods: 45 (per week)                        │
│ • Teaching Periods: 34                                 │
│ • Free Study: 11                                       │
│ • Teachers Involved: 11                                │
│ • Rooms Used: 9                                        │
│                                                        │
│ TEACHER WORKLOAD (for this class only):                │
│ • Mr. Tabe (Math): 6 periods                          │
│ • Mrs. Nkeng (English): 5 periods                     │
│ • Mr. Fon (Physics): 4 periods                        │
│ ... (8 more teachers)                                  │
│                                                        │
│ Status: ⚪ Draft  ⚪ Published                         │
│                                                        │
│ [← BACK]  [SAVE AS DRAFT]  [SAVE & PUBLISH]          │
└────────────────────────────────────────────────────────┘
```

**After Saving:**
- Timetable saved in database
- If "Published", visible to students and teachers in their portals
- Can be edited later if needed

---

### 8.4 Conflict Detection

System automatically detects scheduling conflicts:

**8.4.1 Teacher Double-Booking**

```
┌────────────────────────────────────────────────────────┐
│ ⚠️ SCHEDULING CONFLICT DETECTED                        │
├────────────────────────────────────────────────────────┤
│                                                        │
│ You are trying to assign:                              │
│ • Teacher: Mr. Tabe                                    │
│ • Subject: Mathematics                                 │
│ • Class: Form 3A                                       │
│ • Time: Monday, Period 1 (07:30-08:15)                │
│                                                        │
│ ❌ CONFLICT: Mr. Tabe is already teaching at this time:│
│ • Class: Form 1A                                       │
│ • Subject: Mathematics                                 │
│ • Room: Room 3                                         │
│                                                        │
│ Please choose a different:                             │
│ ⚪ Time slot                                           │
│ ⚪ Teacher (assign different teacher for Form 3A)      │
│                                                        │
│ [CANCEL]  [VIEW MR. TABE'S FULL SCHEDULE]             │
└────────────────────────────────────────────────────────┘
```

**8.4.2 Room Double-Booking**

```
┌────────────────────────────────────────────────────────┐
│ ⚠️ ROOM CONFLICT DETECTED                              │
├────────────────────────────────────────────────────────┤
│                                                        │
│ You are trying to assign:                              │
│ • Room: Lab 1 (Chemistry Lab)                         │
│ • Class: Form 3A (Chemistry)                          │
│ • Time: Tuesday, Period 2 (08:15-09:00)               │
│                                                        │
│ ❌ CONFLICT: Lab 1 is already booked:                  │
│ • Class: Form 4 Science A (Chemistry)                 │
│ • Teacher: Mrs. Ayuk                                   │
│                                                        │
│ Suggestions:                                           │
│ • Use Lab 2 (Physics Lab) if chemistry equipment      │
│   is portable                                          │
│ • Move to different time slot                         │
│ • Schedule as joint class (if same subject/teacher)   │
│                                                        │
│ [CANCEL]  [VIEW LAB AVAILABILITY]  [USE LAB 2]        │
└────────────────────────────────────────────────────────┘
```

---

### 8.5 Joint Classes

For subjects taught to multiple classes together:

```
┌────────────────────────────────────────────────────────┐
│ CREATE JOINT CLASS                                     │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Subject:       [Physics ▼]                            │
│ Teacher:       [Mr. Fon ▼]                            │
│                                                        │
│ SELECT CLASSES TO JOIN:                                │
│ ☑ Form 4 Science A (28 students)                      │
│ ☑ Form 4 Science B (25 students)                      │
│                                                        │
│ Total Students: 53                                     │
│                                                        │
│ Time Slot:     Monday, Period 3 (09:00-09:45)         │
│ Room:          [Lab 2 (Physics) ▼]                    │
│                (Capacity: 60 - OK ✓)                   │
│                                                        │
│ This will:                                             │
│ • Block the same time slot for both classes           │
│ • Assign Mr. Fon to teach both classes together       │
│ • Show as "Physics (Joint)" on both timetables        │
│                                                        │
│ [CANCEL]  [CREATE JOINT CLASS]                        │
└────────────────────────────────────────────────────────┘
```

**Displayed on Timetables:**
- **Form 4 Science A Timetable:** "Physics (Joint with 4 Sci B) - Lab 2"
- **Form 4 Science B Timetable:** "Physics (Joint with 4 Sci A) - Lab 2"

---

### 8.6 Teacher Workload Tracking

**8.6.1 Teacher Workload Dashboard**

```
┌────────────────────────────────────────────────────────────────┐
│ 👨‍🏫 TEACHER WORKLOAD SUMMARY - Session 2024/2025              │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ Teacher            Subject      Classes  Periods/Wk  Status    │
│ ────────────────────────────────────────────────────────────  │
│ Mr. Tabe           Mathematics  5        30         ⚠️ High   │
│ Mrs. Nkeng         English      4        28         OK        │
│ Mr. Fon            Physics      4        24         OK        │
│ Mrs. Ayuk          Chemistry    4        24         OK        │
│ Mr. Njie           Biology      3        20         OK        │
│ Mr. Ashu           Literature   3        18         OK        │
│ Mrs. Tanyi         History      3        15         Low       │
│ Mr. Tikum          Geography    3        15         Low        │
│ Mrs. Akum          French       3        15         Low       │
│ Fr. Mbah           Rel. Studies 5        5          Low       │
│ Coach Fomba        PE           5        5          Low       │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ WORKLOAD THRESHOLDS (Configurable):                            │
│ • Low: < 18 periods/week                                       │
│ • OK: 18-25 periods/week                                       │
│ • High: > 25 periods/week (needs review)                       │
│                                                                │
│ [VIEW DETAILED SCHEDULE]  [EXPORT REPORT]  [REBALANCE]        │
└────────────────────────────────────────────────────────────────┘
```

**8.6.2 Individual Teacher Schedule**

```
┌────────────────────────────────────────────────────────────────┐
│ MR. TABE'S TEACHING SCHEDULE - Mathematics                     │
│ Session 2024/2025 | Total Periods: 30/week                     │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│        Monday    Tuesday   Wednesday Thursday  Friday         │
│ ────────────────────────────────────────────────────────────  │
│ P1     Form 3A   Form 1A   Form 2A     Form 3B   Form 4A     │
│        Room 10   Room 3    Room 7      Room 11  Room 15       │
│                                                                │
│ P2     Form 3A   Form 1A   Form 2A     Form 3B   Form 4A     │
│        Room 10   Room 3    Room 7      Room 11  Room 15       │
│                                                                │
│ P3     Form 1B   Form 3A   Form 1A     Form 2A   Form 1B     │
│        Room 4    Room 10   Room 3      Room 7   Room 4        │
│        ────────   BREAK    ────────                           │
│                                                                │
│ P4     Form 1B   Form 3A   Form 1A     Form 2A   Form 1B     │
│        Room 4    Room 10   Room 3      Room 7   Room 4        │
│                                                                │
│ P5     [FREE]    Form 2A   Form 3B     Form 4A   [FREE]       │
│                 Room 7     Room 11     Room 15                 │
│                                                                │
│ P6     Form 3B   Form 3A   Form 4A     Form 1A   Form 3B     │
│        Room 11   Room 10   Room 15     Room 3   Room 11       │
│        ────────   LUNCH    ────────                           │
│                                                                │
│ P7     Form 4A   Form 3B   [FREE]      Form 3A   Form 2A     │
│        Room 15   Room 11              Room 10   Room 7        │
│                                                                │
│ P8     [FREE]    Form 3A   Form 1B     Form 2A   [FREE]       │
│                 Room 10    Room 4      Room 7                  │
│                                                                │
│ P9     [FREE]    Form 4A   Form 3B     [FREE]    [FREE]       │
│                 Room 15    Room 11                             │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ WEEKLY SUMMARY:                                                │
│ • Teaching Periods: 30                                         │
│ • Free Periods: 15                                             │
│ • Classes Taught: 5 (Forms 1A, 1B, 2A, 3A, 3B, 4A)           │
│ • Total Students: 235                                          │
│                                                                │
│ [PRINT SCHEDULE]  [EMAIL TO TEACHER]  [VIEW CLASS DETAILS]    │
└────────────────────────────────────────────────────────────────┘
```

---

### 8.7 Timetable Templates

Save common timetable structures for reuse:

```
┌────────────────────────────────────────────────────────┐
│ SAVE AS TEMPLATE                                       │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Current Timetable: Form 3A (2024/2025)                │
│                                                        │
│ Template Name: [Form 3 Standard Timetable___]         │
│                                                        │
│ Description:                                           │
│ ┌────────────────────────────────────────────────┐   │
│ │ Standard timetable for Form 3 classes with     │   │
│ │ balanced distribution of subjects.             │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ This template includes:                                │
│ • Subject allocation (periods per week)                │
│ • Typical room assignments                             │
│ • Period distribution across days                      │
│                                                        │
│ ⚠️ Note: Teachers and specific rooms can be            │
│ changed when applying template to new class.           │
│                                                        │
│ [CANCEL]  [SAVE AS TEMPLATE]                          │
└────────────────────────────────────────────────────────┘
```

**Apply Template to New Class:**
```
┌────────────────────────────────────────────────────────┐
│ APPLY TIMETABLE TEMPLATE                               │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Select Template: [Form 3 Standard ▼]                  │
│                                                        │
│ Apply To:        [Form 3B ▼]                          │
│                                                        │
│ The template will be copied with:                      │
│ ✓ Same subject allocation                             │
│ ✓ Same period structure                               │
│                                                        │
│ You will need to:                                      │
│ • Assign specific teachers                            │
│ • Adjust room assignments                             │
│ • Resolve any conflicts                               │
│                                                        │
│ [CANCEL]  [APPLY TEMPLATE]                            │
└────────────────────────────────────────────────────────┘
```

---

### 8.8 Substitution Management

When a teacher is absent:

```
┌────────────────────────────────────────────────────────┐
│ CREATE SUBSTITUTION                                    │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Absent Teacher:    [Mr. Tabe ▼]                       │
│ Subject:           Mathematics                         │
│ Date:              [15/11/2024] 📅                    │
│                                                        │
│ AFFECTED CLASSES (Mr. Tabe's schedule for this day):   │
│ ──────────────────────────────────────────────        │
│ Period  Class     Room      Time                       │
│ ──────────────────────────────────────────────        │
│ ☑ P1    Form 3A   Room 10   07:30-08:15              │
│ ☑ P2    Form 3A   Room 10   08:15-09:00              │
│ ☑ P3    Form 1B   Room 4    09:00-09:45              │
│ ☑ P4    Form 1B   Room 4    10:00-10:45              │
│ ☐ P6    Form 3B   Room 11   11:30-12:15              │
│ ──────────────────────────────────────────────        │
│                                                        │
│ Substitute Teacher: [Mrs. Nkeng ▼]                    │
│                     (Available for selected periods)   │
│                                                        │
│ OR                                                     │
│                                                        │
│ ⚪ Mark as Self-Study (students study independently)   │
│                                                        │
│ Notes:                                                 │
│ ┌────────────────────────────────────────────────┐   │
│ │ Mr. Tabe is attending workshop. Mrs. Nkeng     │   │
│ │ will cover Mathematics for Form 3A and 1B.     │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ [CANCEL]  [CREATE SUBSTITUTION]                       │
└────────────────────────────────────────────────────────┘
```

**After Creating Substitution:**
- Mrs. Nkeng's schedule updated to show substitution
- Form 3A and Form 1B students see "Mathematics (Sub: Mrs. Nkeng)" on timetable for that day
- Notification sent to Mrs. Nkeng
- Substitution logged for payroll (if applicable)

---

### 8.9 Publish Timetables

```
┌────────────────────────────────────────────────────────┐
│ PUBLISH TIMETABLES                                     │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Session: 2024/2025 | Term: First Term                 │
│                                                        │
│ TIMETABLE STATUS:                                      │
│ ──────────────────────────────────────────────        │
│ Total Classes: 12                                      │
│ Completed: 10 (83%)                                    │
│ Draft: 2 (17%)                                         │
│                                                        │
│ Draft Timetables:                                      │
│ • Form 4 Commercial - Not yet finalized               │
│ • Upper Sixth Arts - Missing teacher assignments      │
│                                                        │
│ ────────────────────────────────────────────          │
│                                                        │
│ Do you want to publish completed timetables?           │
│                                                        │
│ ⚪ Publish all completed (10 classes)                  │
│ ⚪ Publish specific classes (select below)             │
│                                                        │
│ When published:                                        │
│ • Timetables visible to students in Student Portal    │
│ • Timetables visible to teachers in Staff Portal      │
│ • Parents can view child's timetable                   │
│ • Printed timetables can be generated                  │
│                                                        │
│ [CANCEL]  [PUBLISH TIMETABLES]                        │
└────────────────────────────────────────────────────────┘
```

---

## 9. ATTENDANCE TRACKING MODULE

### 9.1 Module Overview

The Attendance Tracking module enables period-by-period attendance recording for students. It supports both manual entry and QR code scanning methods.

**Key Features:**
- Period-by-period attendance marking
- Two methods: Manual entry or QR code scanning
- Automatic attendance reports and alerts
- Late arrival tracking
- Attendance integration with promotion criteria
- Parent notifications for absences

---

### 9.2 Attendance Methods

**Method A: Manual Entry by Teacher**
- Teacher marks attendance for each period
- Click-based interface (Present/Absent/Late)

**Method B: QR Code Scanning**
- Each student has QR code on ID card
- Teacher scans QR codes as students enter class
- Faster for large classes

Both methods can be used simultaneously (fallback to manual if QR fails).

---

### 9.3 Manual Attendance Entry

**9.3.1 Teacher Attendance Dashboard**

```
┌────────────────────────────────────────────────────────────────┐
│ 📋 ATTENDANCE - Mr. Tabe (Mathematics)                         │
│ Date: Monday, 18/11/2024                                       │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ YOUR CLASSES TODAY:                                            │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ Period  Time        Class     Students  Marked    Action       │
│ ────────────────────────────────────────────────────────────  │
│ P1      07:30-08:15 Form 3A   43        43/43 ✓  [View]       │
│ P2      08:15-09:00 Form 3A   43        43/43 ✓  [View]       │
│ P3      09:00-09:45 Form 1B   52        0/52  ⚠️  [Mark Now]  │
│ P4      10:00-10:45 Form 1B   52        Not Yet   [Mark]       │
│ P6      11:30-12:15 Form 3B   41        Not Yet   [Mark]       │
│ P7      13:00-13:45 Form 4A   28        Not Yet   [Mark]       │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ ⏰ CURRENT PERIOD: P3 (Form 1B) - Mark attendance now!         │
│                                                                │
│ [MARK ATTENDANCE]                                              │
└────────────────────────────────────────────────────────────────┘
```

---

**9.3.2 Mark Attendance Interface**

```
┌────────────────────────────────────────────────────────────────┐
│ MARK ATTENDANCE - FORM 1B MATHEMATICS                          │
│ Period 3: Monday, 18/11/2024 (09:00-09:45)                    │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ Quick Actions: [MARK ALL PRESENT] [MARK ALL ABSENT]           │
│                                                                │
│ Progress: 48/52 marked (92%) ████████████████░░  │
│                                                                │
│ ────────────────────────────────────────────────────────────  │
│ #  Photo  Name             ID           Status    Time         │
│ ────────────────────────────────────────────────────────────  │
│ 1  [👤]   Ayuk, Peter      SEC/2024/012 Present ✓ 09:02       │
│ 2  [👤]   Doe, Mary        SEC/2024/015 Present ✓ 09:01       │
│ 3  [👤]   Fon, Grace       SEC/2024/018 Late ⚠️   09:12       │
│ 4  [👤]   Mbah, Samuel     SEC/2024/021 Absent ✗              │
│ 5  [👤]   Nkeng, Rita      SEC/2024/024 Present ✓ 09:03       │
│ 6  [👤]   Tanyi, Paul      SEC/2024/027 [Mark ▼]             │
│                                          ┌─────────────┐       │
│                                          │ Present     │       │
│                                          │ Absent      │       │
│                                          │ Late        │       │
│                                          └─────────────┘       │
│ 7  [👤]   Tabe, Grace      SEC/2024/030 [Mark ▼]             │
│ ... (45 more students)                                         │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ Showing 1-7 of 52 | [← Previous] [1] [2] [3] [Next →]        │
│                                                                │
│ SUMMARY:                                                       │
│ • Present: 47 (90%)                                            │
│ • Late: 1 (2%)                                                 │
│ • Absent: 4 (8%)                                               │
│                                                                │
│ [SAVE DRAFT]  [SUBMIT ATTENDANCE]                             │
└────────────────────────────────────────────────────────────────┘
```

**How it Works:**
- Teacher clicks dropdown for each student
- Selects: Present, Absent, or Late
- If Late, system records time student marked present
- Can save draft and continue later
- Submit when complete (locks attendance for that period)

---

### 9.4 QR Code Attendance Scanning

**9.4.1 Student ID Card with QR Code**

```
┌───────────────────────────────────┐
│  ST. JOSEPH'S SECONDARY SCHOOL   │
│                                   │
│  ┌─────────┐                      │
│  │         │  JOHN DOE            │
│  │ Photo   │  SEC/2024/045        │
│  │         │  Form 3A             │
│  └─────────┘  Day Student         │
│                                   │
│  ┌─────────────────┐              │
│  │ █▀▀▀█ ▀▀  █▀▀▀█ │              │
│  │ █   █ ▄▀▄ █   █ │  ← QR Code   │
│  │ █   █  ▀  █   █ │              │
│  │ █▄▄▄█ ▄██ █▄▄▄█ │              │
│  └─────────────────┘              │
│                                   │
│  Valid: 2024/2025                 │
│  Emergency: +237 670 123 456      │
└───────────────────────────────────┘
```

QR Code contains:
```json
{
  "student_id": "SEC/2024/045",
  "name": "John Doe",
  "class": "Form 3A",
  "session": "2024/2025"
}
```

---

**9.4.2 QR Attendance Scanning Interface**

```
┌────────────────────────────────────────────────────────┐
│ 📷 SCAN QR ATTENDANCE - FORM 3A MATHEMATICS            │
│ Period 1: Monday, 18/11/2024 (07:30-08:15)            │
├────────────────────────────────────────────────────────┤
│                                                        │
│ ┌──────────────────────────────────────────────┐     │
│ │                                              │     │
│ │          [CAMERA VIEWFINDER]                 │     │
│ │                                              │     │
│ │     Point camera at student's ID card        │     │
│ │                                              │     │
│ │              ┌────────┐                      │     │
│ │              │  Scan  │                      │     │
│ │              │  Zone  │                      │     │
│ │              └────────┘                      │     │
│ │                                              │     │
│ └──────────────────────────────────────────────┘     │
│                                                        │
│ [START CAMERA]  [STOP]  [SWITCH TO MANUAL ENTRY]      │
│                                                        │
│ ────────────────────────────────────────────          │
│ SCANNED STUDENTS (39/43):                              │
│ ────────────────────────────────────────────          │
│                                                        │
│ ✓ 09:01 - Doe, John (SEC/2024/045)                   │
│ ✓ 09:01 - Tanyi, Mary (SEC/2024/046)                 │
│ ✓ 09:02 - Ayuk, Grace (SEC/2024/048)                 │
│ ✓ 09:02 - Fon, Peter (SEC/2024/052)                  │
│ ... (35 more)                                          │
│                                                        │
│ ⚠️ NOT YET SCANNED (4):                                │
│ • Mbah, Clara (SEC/2024/055)                          │
│ • Nkeng, Paul (SEC/2024/058)                          │
│ • Tabe, Samuel (SEC/2024/061)                         │
│ • Tikum, Rita (SEC/2024/064)                          │
│                                                        │
│ [MARK REMAINING AS ABSENT]  [CONTINUE SCANNING]       │
│                             [SUBMIT ATTENDANCE]        │
└────────────────────────────────────────────────────────┘
```

**Process:**
1. Teacher opens scanning interface
2. Activates camera on tablet/phone
3. Students show ID cards as they enter class
4. Teacher scans each QR code (takes <1 second per student)
5. System automatically marks student as "Present"
6. After 10-15 minutes, teacher marks remaining students as "Absent"
7. Submits attendance

**Benefits:**
- Much faster than manual (39 students in ~5 minutes vs 15 minutes)
- Less errors (no manual clicking)
- Automatic timestamp for late arrivals

---

### 9.5 Attendance Reports

**9.5.1 Student Attendance Summary**

```
┌────────────────────────────────────────────────────────┐
│ ATTENDANCE SUMMARY: John Doe (SEC/2024/045)            │
│ Session: 2024/2025 | Term: First Term                 │
├────────────────────────────────────────────────────────┤
│                                                        │
│ OVERALL ATTENDANCE:                                    │
│ ──────────────────────────────────────────────        │
│ Total Teaching Days: 48                                │
│ Days Present: 45 (94%)                                 │
│ Days Late: 2 (4%)                                      │
│ Days Absent: 3 (6%)                                    │
│                                                        │
│ ████████████████████████████░░  94% Present           │
│                                                        │
│ STATUS: ✅ GOOD (Above 90% threshold)                  │
│                                                        │
│ ────────────────────────────────────────────          │
│ ABSENCE BREAKDOWN:                                     │
│ ────────────────────────────────────────────          │
│ Date        Periods Absent  Reason                     │
│ ────────────────────────────────────────────          │
│ 15/10/2024  All Day (9)     Sick (Medical cert. ✓)    │
│ 22/10/2024  Periods 1-3     Family emergency          │
│ 05/11/2024  Period 7        Left early (with perm.)   │
│ ────────────────────────────────────────────          │
│                                                        │
│ LATE ARRIVALS:                                         │
│ • 18/10/2024 - Arrived 09:15 (late for P3)            │
│ • 29/10/2024 - Arrived 08:30 (late for P2)            │
│                                                        │
│ PARENT NOTIFICATIONS SENT:                             │
│ • 3 absence alerts sent via SMS                        │
│ • 1 attendance warning (after 2nd absence)            │
│                                                        │
│ [EXPORT REPORT]  [SEND TO PARENT]                     │
└────────────────────────────────────────────────────────┘
```

---

**9.5.2 Class Attendance Summary**

```
┌────────────────────────────────────────────────────────────────┐
│ CLASS ATTENDANCE SUMMARY - FORM 3A                             │
│ Session 2024/2025 | Term: First Term                          │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ Teaching Days: 48 | Total Students: 43                         │
│                                                                │
│ OVERALL CLASS ATTENDANCE: 92% ████████████████████░░          │
│                                                                │
│ ────────────────────────────────────────────────────────────  │
│ Student         ID           Days      %      Status           │
│                              Present                           │
│ ────────────────────────────────────────────────────────────  │
│ Tanyi, Mary     SEC/2024/046 48/48    100%   ✅ Perfect        │
│ Mbah, Clara     SEC/2024/055 48/48    100%   ✅ Perfect        │
│ Ashu, Rita      SEC/2024/064 47/48    98%    ✅ Excellent      │
│ Ayuk, Grace     SEC/2024/048 47/48    98%    ✅ Excellent      │
│ Doe, John       SEC/2024/045 45/48    94%    ✅ Good           │
│ ... (35 more students)                                         │
│ Fon, Peter      SEC/2024/052 40/48    83%    ⚠️ Fair          │
│ Nkeng, Paul     SEC/2024/058 35/48    73%    ⚠️ Poor          │
│ Tikum, Samuel   SEC/2024/089 30/48    63%    ❌ Very Poor     │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ ATTENDANCE DISTRIBUTION:                                       │
│ • Perfect (100%): 2 students (5%)                             │
│ • Excellent (95-99%): 18 students (42%)                       │
│ • Good (90-94%): 15 students (35%)                            │
│ • Fair (80-89%): 5 students (12%)                             │
│ • Poor (<80%): 3 students (7%) ⚠️                             │
│                                                                │
│ ⚠️ STUDENTS AT RISK (Below 85%):                               │
│ System recommends intervention for 3 students.                 │
│                                                                │
│ [VIEW DETAILS]  [EXPORT REPORT]  [NOTIFY PARENTS]             │
└────────────────────────────────────────────────────────────────┘
```

---

### 9.6 Automatic Alerts & Notifications

**9.6.1 Parent SMS Notification (Absence)**

```
SMS to Parent (Jane Doe - +237 670 123 456):

St. Joseph's Secondary School
────────────────────────────
Dear Parent,

Your child JOHN DOE (SEC/2024/045) was absent 
from school today, 15/11/2024.

If this was planned, please ignore this message.
Otherwise, please contact the school.

For emergencies: +237 233 XX XX XX
```

**9.6.2 Attendance Warning (Multiple Absences)**

```
EMAIL to Parent (jane.doe@example.com):

Subject: Attendance Warning - John Doe (SEC/2024/045)

Dear Mrs. Jane Doe,

This is to inform you that your child JOHN DOE has been 
absent for 5 days this term (10% of teaching days).

School policy requires at least 85% attendance for 
promotion eligibility.

Current attendance: 90% (43/48 days present)

Please ensure regular school attendance. If there are
issues affecting attendance, please contact the school.

Regards,
Mrs. Ayuk (Class Teacher - Form 3A)
St. Joseph's Secondary School
```

---

This completes Sections 8 and 9! Should I continue with Sections 10 and 11 to finish all core modules?

---

## 10. STAFF MANAGEMENT & PAYROLL MODULE

### 10.1 Module Overview

The Staff Management & Payroll module handles employee information, role assignments, teaching load tracking, and basic payroll processing.

**Key Features:**
- Staff profiles with qualifications
- Role-based access control (RBAC)
- Teaching hours tracking (linked to timetable)
- Basic payroll (salary, deductions, allowances)
- Payslip generation
- Staff attendance tracking
- Leave management

---

### 10.2 Staff Profiles

**10.2.1 Create Staff Profile**

```
┌────────────────────────────────────────────────────────┐
│ ADD NEW STAFF MEMBER                                   │
├────────────────────────────────────────────────────────┤
│                                                        │
│ PERSONAL INFORMATION                                   │
│ ──────────────────────────────────────────────        │
│ Staff ID:         [AUTO-GENERATED]                    │
│                   (e.g., STF/2024/001)                 │
│                                                        │
│ Full Name:        [____________________________]  *   │
│ Date of Birth:    [DD/MM/YYYY] 📅  *                  │
│ Gender:           ⚪ Male  ⚪ Female  *                │
│ Nationality:      [Cameroonian ▼]                     │
│                                                        │
│ Contact:                                               │
│ Phone:            [+237 6XX XXX XXX_________]  *      │
│ Email:            [____________________________]  *   │
│ Address:          [____________________________]      │
│                                                        │
│ Photo:            [Upload Photo] 📁                   │
│                                                        │
│ ────────────────────────────────────────────          │
│ EMPLOYMENT INFORMATION                                 │
│ ──────────────────────────────────────────────        │
│ Employment Type:  ⚪ Full-Time  ⚪ Part-Time           │
│                   ⚪ Contract                          │
│                                                        │
│ Position:         [Mathematics Teacher ▼]  *          │
│                   (Teacher, Admin, Support)            │
│                                                        │
│ Department:       [Science Department ▼]              │
│                                                        │
│ Date Joined:      [DD/MM/YYYY] 📅  *                  │
│                                                        │
│ ────────────────────────────────────────────          │
│ QUALIFICATIONS                                         │
│ ──────────────────────────────────────────────        │
│ Highest Degree:   [Bachelor's Degree ▼]              │
│ Institution:      [University of Buea__________]      │
│ Year Graduated:   [2020___]                           │
│ Major/Field:      [Mathematics_____________]          │
│                                                        │
│ Teaching Certificate:                                  │
│ ☑ HTC (Higher Teachers Certificate)                   │
│ ☐ DIPES I                                             │
│ ☐ DIPES II                                            │
│ ☐ Other: [Specify___________]                         │
│                                                        │
│ Years of Experience: [5__] years                      │
│                                                        │
│ Documents:                                             │
│ [Upload CV] 📁  [Upload Certificates] 📁              │
│                                                        │
│ [CANCEL]  [SAVE STAFF PROFILE]                        │
└────────────────────────────────────────────────────────┘
```

---

**10.2.2 Staff List View**

```
┌────────────────────────────────────────────────────────────────┐
│ 👥 STAFF MANAGEMENT                                            │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ Total Staff: 45 | Active: 42 | On Leave: 3                    │
│                                                                │
│ Quick Filters:                                                 │
│ ⚪ All Staff  ⚪ Teachers  ⚪ Admin  ⚪ Support                 │
│                                                                │
│ ────────────────────────────────────────────────────────────  │
│ Photo  Name          ID          Role          Status  Actions│
│ ────────────────────────────────────────────────────────────  │
│ [👤]   Tabe, John    STF/2020/01 Math Teacher  Active  [View] │
│ [👤]   Nkeng, Grace  STF/2019/05 Eng Teacher   Active  [View] │
│ [👤]   Ayuk, Mary    STF/2020/12 Bursar        Active  [View] │
│ [👤]   Fon, Peter    STF/2018/08 Phys Teacher  Active  [View] │
│ [👤]   Mbah, Rev.    STF/2015/01 Principal     Active  [View] │
│ ... (40 more staff members)                                    │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ [+ ADD STAFF]  [EXPORT LIST]  [PRINT DIRECTORY]               │
└────────────────────────────────────────────────────────────────┘
```

---

### 10.3 Role-Based Access Control (RBAC)

**10.3.1 Assign Role to Staff**

```
┌────────────────────────────────────────────────────────┐
│ ASSIGN ROLE: Mr. Tabe (STF/2020/01)                   │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Current Role: Mathematics Teacher                      │
│                                                        │
│ SELECT ROLE(S):                                        │
│ ──────────────────────────────────────────────        │
│ ☑ Subject Teacher                                     │
│   • Can enter marks                                   │
│   • Can view class rosters                            │
│   • Can mark attendance                               │
│                                                        │
│ ☑ Class Teacher (Form 3A)                             │
│   • All Subject Teacher permissions +                 │
│   • Can write report card remarks                     │
│   • Can view full class performance                   │
│   • Can contact parents                               │
│                                                        │
│ ☐ HOD (Head of Department) - Science                  │
│   • Can approve marks in department                   │
│   • Can view all department teachers                  │
│                                                        │
│ ☐ Vice Principal (Academic)                           │
│   • Can approve all marks                             │
│   • Can publish results                               │
│   • Full academic oversight                           │
│                                                        │
│ ☐ Principal                                           │
│   • Full system access                                │
│   • Can approve fee waivers                           │
│   • Can graduate students                             │
│                                                        │
│ ☐ Bursar                                              │
│   • Can record payments                               │
│   • Can generate financial reports                    │
│   • Can manage fee structures                         │
│                                                        │
│ ☐ Front Desk                                          │
│   • Can view student information                      │
│   • Can print report cards (if fees cleared)          │
│   • Cannot edit student data                          │
│                                                        │
│ [CANCEL]  [SAVE ROLE ASSIGNMENT]                      │
└────────────────────────────────────────────────────────┘
```

**Role Permission Matrix:**

```
PERMISSION MATRIX
────────────────────────────────────────────────────────────────
Permission             Principal VP-Acad HOD  Class Subj Bursar
                                              Tchr  Tchr
────────────────────────────────────────────────────────────────
View Students          ✓        ✓      ✓    ✓     ✓    ✓
Edit Students          ✓        ✓      ✗    ✗     ✗    ✗
Enter Marks            ✗        ✗      ✗    ✗     ✓    ✗
Approve Marks          ✓        ✓      ✓    ✗     ✗    ✗
Publish Results        ✓        ✓      ✗    ✗     ✗    ✗
Record Payments        ✓        ✗      ✗    ✗     ✗    ✓
Manage Fees            ✓        ✗      ✗    ✗     ✗    ✓
Generate Transcripts   ✓        ✓      ✗    ✗     ✗    ✗
Promote Students       ✓        ✓      ✗    ✗     ✗    ✗
Mark Attendance        ✗        ✗      ✗    ✗     ✓    ✗
Create Timetables      ✓        ✓      ✗    ✗     ✗    ✗
────────────────────────────────────────────────────────────────
```

---

### 10.4 Teaching Hours Tracking

**10.4.1 Individual Teacher Teaching Load**

```
┌────────────────────────────────────────────────────────┐
│ TEACHING LOAD: Mr. Tabe (Mathematics)                  │
│ Session 2024/2025                                      │
├────────────────────────────────────────────────────────┤
│                                                        │
│ ASSIGNED CLASSES (5):                                  │
│ ──────────────────────────────────────────────        │
│ Class       Students  Periods/Week  Total Hours        │
│ ──────────────────────────────────────────────        │
│ Form 1A     48        6             4.5 hrs           │
│ Form 1B     52        6             4.5 hrs           │
│ Form 2A     45        6             4.5 hrs           │
│ Form 3A     43        6             4.5 hrs           │
│ Form 4 Sci  28        6             4.5 hrs           │
│ ──────────────────────────────────────────────        │
│ TOTAL:      216       30            22.5 hrs/week     │
│                                                        │
│ WORKLOAD STATUS: ⚠️ HIGH (Exceeds 25 periods/week)    │
│                                                        │
│ BREAKDOWN BY DAY:                                      │
│ • Monday: 6 periods (4.5 hrs)                         │
│ • Tuesday: 7 periods (5.25 hrs)                       │
│ • Wednesday: 6 periods (4.5 hrs)                      │
│ • Thursday: 6 periods (4.5 hrs)                       │
│ • Friday: 5 periods (3.75 hrs)                        │
│                                                        │
│ FREE PERIODS: 15/week                                  │
│                                                        │
│ [VIEW DETAILED SCHEDULE]  [EXPORT REPORT]             │
└────────────────────────────────────────────────────────┘
```

---

### 10.5 Basic Payroll System

**10.5.1 Payroll Settings (Per Staff)**

```
┌────────────────────────────────────────────────────────┐
│ PAYROLL SETTINGS: Mr. Tabe (STF/2020/01)              │
├────────────────────────────────────────────────────────┤
│                                                        │
│ SALARY STRUCTURE                                       │
│ ──────────────────────────────────────────────        │
│ Base Salary:          [180,000__] XAF/month  *        │
│                                                        │
│ ALLOWANCES (Monthly):                                  │
│ ──────────────────────────────────────────────        │
│ Transport Allowance:  [20,000___] XAF                 │
│ Housing Allowance:    [30,000___] XAF                 │
│ Teaching Load Bonus:  [15,000___] XAF                 │
│                       (30 periods × 500 XAF)           │
│                                                        │
│ [+ ADD ALLOWANCE]                                      │
│                                                        │
│ GROSS SALARY:         245,000 XAF/month               │
│                                                        │
│ ────────────────────────────────────────────          │
│ DEDUCTIONS (Monthly):                                  │
│ ──────────────────────────────────────────────        │
│ CNPS (Social Security): [Auto: 4.2%]  10,290 XAF     │
│ Income Tax:             [Auto: 10%]   24,500 XAF     │
│ Staff Loan Repayment:   [5,000___]    5,000 XAF      │
│                                                        │
│ [+ ADD DEDUCTION]                                      │
│                                                        │
│ TOTAL DEDUCTIONS:     39,790 XAF                      │
│                                                        │
│ ────────────────────────────────────────────          │
│ NET SALARY:           205,210 XAF/month               │
│                                                        │
│ Payment Method:                                        │
│ ⚪ Bank Transfer  ⚪ Cash  ⚪ Mobile Money             │
│                                                        │
│ Bank Details (if transfer):                            │
│ Bank Name:    [UBA Cameroon_____________]             │
│ Account No:   [10234567890123__________]              │
│ Account Name: [Tabe John_______________]              │
│                                                        │
│ [CANCEL]  [SAVE PAYROLL SETTINGS]                     │
└────────────────────────────────────────────────────────┘
```

---

**10.5.2 Generate Monthly Payroll**

```
┌────────────────────────────────────────────────────────┐
│ GENERATE PAYROLL - NOVEMBER 2024                       │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Month/Year:   [November ▼] [2024 ▼]                  │
│                                                        │
│ SELECT STAFF:                                          │
│ ⚪ All Active Staff (42)                               │
│ ⚪ By Department: [Select ▼]                          │
│ ⚪ Individual: [Search ▼]                             │
│                                                        │
│ PAYROLL SUMMARY:                                       │
│ ──────────────────────────────────────────────        │
│ Total Staff: 42                                        │
│ Total Gross Pay: 8,960,000 XAF                        │
│ Total Deductions: 1,342,000 XAF                       │
│ Total Net Pay: 7,618,000 XAF                          │
│                                                        │
│ BREAKDOWN BY CATEGORY:                                 │
│ • Teachers (28): 5,600,000 XAF                        │
│ • Admin (8): 1,680,000 XAF                            │
│ • Support (6): 738,000 XAF                            │
│                                                        │
│ Include in Payroll:                                    │
│ ☑ Base Salary                                         │
│ ☑ Allowances                                          │
│ ☑ Bonuses (if any)                                    │
│ ☑ Deductions (CNPS, Tax, Loans)                       │
│                                                        │
│ [PREVIEW PAYROLL]  [GENERATE PAYSLIPS]                │
│                    [EXPORT TO EXCEL]                   │
└────────────────────────────────────────────────────────┘
```

---

**10.5.3 Payslip (Individual)**

```
═══════════════════════════════════════════════════════
          ST. JOSEPH'S SECONDARY SCHOOL
            MONTHLY PAYSLIP - NOVEMBER 2024
═══════════════════════════════════════════════════════

EMPLOYEE INFORMATION
───────────────────────────────────────────────────────
Name:           TABE, JOHN
Staff ID:       STF/2020/01
Position:       Mathematics Teacher
Department:     Science
Bank Account:   UBA - 10234567890123

───────────────────────────────────────────────────────
EARNINGS
───────────────────────────────────────────────────────
Description                              Amount (XAF)
───────────────────────────────────────────────────────
Base Salary                              180,000
Transport Allowance                       20,000
Housing Allowance                         30,000
Teaching Load Bonus (30 periods)          15,000
                                         ─────────
GROSS PAY                                245,000

───────────────────────────────────────────────────────
DEDUCTIONS
───────────────────────────────────────────────────────
Description                              Amount (XAF)
───────────────────────────────────────────────────────
CNPS (4.2%)                               10,290
Income Tax (10%)                          24,500
Staff Loan Repayment                       5,000
                                         ─────────
TOTAL DEDUCTIONS                          39,790

───────────────────────────────────────────────────────
NET PAY                                  205,210 XAF
───────────────────────────────────────────────────────

Payment Method: Bank Transfer
Payment Date: 30/11/2024
Payment Status: ✅ PAID

───────────────────────────────────────────────────────
This is a computer-generated payslip. No signature required.
Generated: 28/11/2024 at 14:30
Payslip ID: PS/2024/11/001
═══════════════════════════════════════════════════════
```

---

### 10.6 Staff Attendance

**10.6.1 Mark Staff Attendance**

```
┌────────────────────────────────────────────────────────────────┐
│ STAFF ATTENDANCE - Monday, 18/11/2024                          │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ Quick Actions: [MARK ALL PRESENT]  [EXPORT]                   │
│                                                                │
│ Progress: 40/42 marked (95%) ███████████████████░             │
│                                                                │
│ ────────────────────────────────────────────────────────────  │
│ #  Photo  Name           Role          Status      Time In    │
│ ────────────────────────────────────────────────────────────  │
│ 1  [👤]   Tabe, John     Math Teacher  Present ✓   07:15     │
│ 2  [👤]   Nkeng, Grace   Eng Teacher   Present ✓   07:20     │
│ 3  [👤]   Ayuk, Mary     Bursar        Present ✓   07:25     │
│ 4  [👤]   Fon, Peter     Phys Teacher  Late ⚠️     07:45     │
│ 5  [👤]   Mbah, Rev.     Principal     Present ✓   07:10     │
│ 6  [👤]   Njie, Paul     Bio Teacher   Absent ✗              │
│                                          On Leave (Medical)    │
│ 7  [👤]   Ashu, Samuel   Lit Teacher   Present ✓   07:22     │
│ ... (35 more staff)                                            │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ SUMMARY:                                                       │
│ • Present: 39 (93%)                                            │
│ • Late: 1 (2%)                                                 │
│ • Absent: 2 (5%)                                               │
│   - 1 on approved leave                                        │
│   - 1 unexcused                                                │
│                                                                │
│ [SAVE]  [SEND ABSENCE ALERTS TO ADMIN]                        │
└────────────────────────────────────────────────────────────────┘
```

---

## 11. PROMOTION & PROGRESSION MODULE

### 11.1 Module Overview

The Promotion & Progression module manages end-of-year student progression from one form to the next, including stream assignment for students moving from Form 3 to Form 4.

**Key Features:**
- Automated promotion suggestions based on criteria
- Manual promotion decisions by admin
- Stream assignment (Form 3 → Form 4, Form 5 → Lower Sixth)
- Repetition handling (students who didn't meet criteria)
- Appeal process for stream assignments
- Bulk promotion processing
- Next year class creation
- Fee reassignment after promotion

---

### 11.2 Promotion Criteria

**11.2.1 Standard Promotion Requirements**

```
PROMOTION CRITERIA (Configurable in System Settings)
────────────────────────────────────────────────────────
1. ACADEMIC PERFORMANCE
   • Term average ≥ 10.0/20 (Pass mark)
   • If average = 9.xx, subject to admin discretion

2. FEE CLEARANCE
   • All fees for current session must be paid
   • Or approved payment plan in place

3. ATTENDANCE
   • Minimum 85% attendance required
   • Excused absences (medical, family emergency) excluded

4. CONDUCT
   • No major disciplinary issues
   • Good behavior record

SPECIAL CASES:
• Form 3 → Form 4: Must select stream (Science, Arts, Commercial)
• Form 5 → Lower Sixth: GCE O-Level results considered
• Upper Sixth: Graduate (do not promote)
```

---

### 11.3 Promotion Workflow (End of Year)

**11.3.1 System Generates Promotion Suggestions**

At end of Third Term:

```
┌────────────────────────────────────────────────────────────────┐
│ 🎓 PROMOTION SUGGESTIONS - SESSION 2024/2025                   │
│ Generated: 30/06/2025                                          │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ Total Students: 1,189                                          │
│                                                                │
│ PROMOTION ELIGIBILITY SUMMARY:                                 │
│ ──────────────────────────────────────────────────────────    │
│ ✅ Meets All Criteria: 1,050 students (88%)                    │
│ ⚠️ Needs Review: 89 students (7%)                              │
│ ❌ Does Not Meet Criteria: 50 students (4%)                    │
│                                                                │
│ BREAKDOWN BY FORM:                                             │
│ ────────────────────────────────────────────────────────────  │
│ Form  Students  Promote  Review  Repeat  Graduate             │
│ ────────────────────────────────────────────────────────────  │
│ F1    100       92       5        3        -                  │
│ F2    95        87       4        4        -                  │
│ F3    85        75       6        4        -  (Stream needed) │
│ F4    90        80       6        4        -                  │
│ F5    95        85       7        3        -  (GCE results)   │
│ L6    30        28       1        1        -                  │
│ U6    18        -        -        -        18 (GRADUATE)      │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ STUDENTS NEEDING REVIEW (89):                                  │
│ • Borderline average (9.0-9.9): 45 students                   │
│ • Fee owing but payment plan: 22 students                     │
│ • Attendance 80-84%: 15 students                              │
│ • Conduct issues: 7 students                                  │
│                                                                │
│ [VIEW DETAILS BY CLASS]  [START PROMOTION PROCESS]            │
│                          [EXPORT REPORT]                       │
└────────────────────────────────────────────────────────────────┘
```

---

**11.3.2 Review Individual Student for Promotion**

```
┌────────────────────────────────────────────────────────┐
│ PROMOTION REVIEW: John Doe (SEC/2024/045)              │
│ Current: Form 3A | Proposed: Form 4                    │
├────────────────────────────────────────────────────────┤
│                                                        │
│ PROMOTION CRITERIA CHECK:                              │
│ ──────────────────────────────────────────────        │
│                                                        │
│ ✅ ACADEMIC PERFORMANCE                                │
│ • Term 1 Average: 14.3/20                             │
│ • Term 2 Average: 14.8/20                             │
│ • Term 3 Average: 15.2/20                             │
│ • Year Average: 14.8/20 ✓                             │
│ • Class Rank: 8/43                                     │
│                                                        │
│ ✅ FEE CLEARANCE                                       │
│ • Total Fees (All 3 terms): 183,000 XAF               │
│ • Amount Paid: 183,000 XAF ✓                          │
│ • Balance: 0 XAF                                       │
│                                                        │
│ ✅ ATTENDANCE                                          │
│ • Days Present: 137/145 (94%) ✓                       │
│ • Threshold: 85%                                       │
│                                                        │
│ ✅ CONDUCT                                             │
│ • Disciplinary Record: Clean ✓                        │
│ • Behavior: Good                                       │
│                                                        │
│ ────────────────────────────────────────────          │
│                                                        │
│ 🎯 RECOMMENDATION: PROMOTE TO FORM 4                   │
│                                                        │
│ ────────────────────────────────────────────          │
│ STREAM ASSIGNMENT (Required for Form 4):               │
│ ──────────────────────────────────────────────        │
│                                                        │
│ Based on performance:                                  │
│ • Mathematics: 14.5/20                                │
│ • Sciences (Phys, Chem, Bio): 14.3/20 avg             │
│ • Arts (Lit, Hist, Geo): 13.5/20 avg                  │
│                                                        │
│ SUGGESTED STREAM: Science                              │
│ (Strong performance in Math and Sciences)              │
│                                                        │
│ Select Stream:                                         │
│ ⚪ Science (Suggested ✓)                              │
│ ⚪ Arts                                                │
│ ⚪ Commercial                                          │
│                                                        │
│ Admin Decision:                                        │
│ ⚪ APPROVE Promotion to Form 4 Science                 │
│ ⚪ DEFER Decision (request more info)                  │
│ ⚪ DENY Promotion (repeat Form 3)                      │
│                                                        │
│ Comments:                                              │
│ ┌────────────────────────────────────────────────┐   │
│ │ Student has excellent academic record.         │   │
│ │ Approved for promotion to Form 4 Science.      │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ [CANCEL]  [SAVE DECISION]                             │
└────────────────────────────────────────────────────────┘
```

---

**11.3.3 Borderline Student (Needs Admin Discretion)**

```
┌────────────────────────────────────────────────────────┐
│ ⚠️ PROMOTION REVIEW: Mary Tanyi (SEC/2024/046)        │
│ Current: Form 2A | Proposed: Form 3                    │
├────────────────────────────────────────────────────────┤
│                                                        │
│ PROMOTION CRITERIA CHECK:                              │
│ ──────────────────────────────────────────────        │
│                                                        │
│ ⚠️ ACADEMIC PERFORMANCE - BORDERLINE                   │
│ • Year Average: 9.7/20 ⚠️                              │
│ • Pass Mark: 10.0/20                                   │
│ • Class Rank: 38/45 (Bottom quartile)                 │
│                                                        │
│ SUBJECT BREAKDOWN:                                     │
│ Strong Subjects:                                       │
│ • French: 14.0/20                                     │
│ • Religious Studies: 13.5/20                          │
│                                                        │
│ Weak Subjects:                                        │
│ • Mathematics: 7.5/20 ❌ (Failed)                     │
│ • Physics: 8.0/20 ❌ (Failed)                         │
│ • English: 9.0/20 (Below pass)                        │
│                                                        │
│ ✅ FEE CLEARANCE                                       │
│ • Fully Paid ✓                                        │
│                                                        │
│ ✅ ATTENDANCE                                          │
│ • 96% (Excellent) ✓                                   │
│                                                        │
│ ✅ CONDUCT                                             │
│ • Good behavior ✓                                      │
│                                                        │
│ ────────────────────────────────────────────          │
│                                                        │
│ 🤔 DECISION REQUIRED:                                  │
│                                                        │
│ Options:                                               │
│ ⚪ PROMOTE with Remedial Classes                       │
│    (Assign extra math/physics tutoring in Form 3)     │
│                                                        │
│ ⚪ CONDITIONAL PROMOTION                                │
│    (Must improve to ≥10 avg by Term 1 of Form 3)      │
│                                                        │
│ ⚪ REPEAT Form 2                                       │
│    (Recommended if student struggles with basics)      │
│                                                        │
│ Admin Comments:                                        │
│ ┌────────────────────────────────────────────────┐   │
│ │ Student works hard but struggles with STEM     │   │
│ │ subjects. Approve promotion with mandatory     │   │
│ │ remedial math classes.                         │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ [CANCEL]  [SAVE DECISION]  [NOTIFY PARENT]           │
└────────────────────────────────────────────────────────┘
```

---

### 11.4 Bulk Promotion Process

**11.4.1 Bulk Promote Class**

```
┌────────────────────────────────────────────────────────────────┐
│ BULK PROMOTION - FORM 3A                                       │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ Current Class: Form 3A (43 students)                           │
│ Session: 2024/2025 → 2025/2026                                │
│                                                                │
│ AUTOMATIC RECOMMENDATIONS:                                     │
│ ──────────────────────────────────────────────────────────    │
│ ✅ Auto-Promote (35 students):                                 │
│ Meets all criteria, no issues                                  │
│                                                                │
│ ⚠️ Needs Review (5 students):                                  │
│ • Borderline average (3)                                       │
│ • Fee owing (1)                                                │
│ • Low attendance (1)                                           │
│                                                                │
│ ❌ Recommend Repeat (3 students):                              │
│ • Failed average (<10.0)                                       │
│ • Multiple subject failures                                    │
│                                                                │
│ ────────────────────────────────────────────────────────────  │
│ STREAM ASSIGNMENT (Required for Form 4):                       │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ Auto-Suggested Streams (based on performance):                 │
│ • Science: 18 students                                         │
│ • Arts: 12 students                                            │
│ • Commercial: 5 students                                       │
│ • Undecided: 5 (need review)                                   │
│                                                                │
│ You can:                                                       │
│ ⚪ Accept all auto-suggestions                                 │
│ ⚪ Review and adjust individually                              │
│                                                                │
│ ────────────────────────────────────────────────────────────  │
│ NEW CLASSES FOR 2025/2026:                                     │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ Students will be distributed to:                               │
│ • Form 4 Science A (20 students) - NEW CLASS                  │
│ • Form 4 Arts A (12 students) - NEW CLASS                     │
│ • Form 4 Commercial (5 students) - Existing class             │
│                                                                │
│ Class Teachers:                                                │
│ • Form 4 Science A: [Select Teacher ▼_______]                 │
│ • Form 4 Arts A: [Select Teacher ▼_______]                    │
│                                                                │
│ ────────────────────────────────────────────────────────────  │
│ FEE REASSIGNMENT:                                              │
│ ────────────────────────────────────────────────────────────  │
│                                                                │
│ Current Fees (Form 3): 61,000 XAF/term                        │
│ New Fees (Form 4): 70,000 XAF/term                            │
│                                                                │
│ ☑ Automatically assign Term 1 fees for 2025/2026              │
│ ☑ Send fee notification to parents                            │
│                                                                │
│ [CANCEL]  [REVIEW INDIVIDUALLY]  [PROCESS BULK PROMOTION]     │
└────────────────────────────────────────────────────────────────┘
```

---

**11.4.2 Promotion Confirmation**

```
┌────────────────────────────────────────────────────────┐
│ ✅ PROMOTION COMPLETED - FORM 3A                       │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Processed: 43 students                                 │
│                                                        │
│ RESULTS:                                               │
│ ──────────────────────────────────────────────        │
│ ✅ Promoted: 38 students                               │
│    • Form 4 Science A: 18                             │
│    • Form 4 Arts A: 12                                │
│    • Form 4 Commercial: 8                             │
│                                                        │
│ ⚠️ Deferred: 2 students (pending review)               │
│                                                        │
│ ❌ Repeat Form 3: 3 students                           │
│                                                        │
│ ACTIONS COMPLETED:                                     │
│ ✓ New session enrollments created (2025/2026)         │
│ ✓ Students assigned to new classes                    │
│ ✓ Subjects assigned based on streams                  │
│ ✓ Term 1 fees assigned (70,000 XAF each)             │
│ ✓ Email notifications sent to parents                 │
│ ✓ SMS sent to 5 parents (no email)                    │
│                                                        │
│ NEXT STEPS:                                            │
│ • Create timetables for new Form 4 classes           │
│ • Assign teachers to subjects                         │
│ • Print new student ID cards (optional)               │
│                                                        │
│ [VIEW PROMOTED STUDENTS]  [PRINT PROMOTION REPORT]    │
│                          [CLOSE]                       │
└────────────────────────────────────────────────────────┘
```

---

### 11.5 Stream Assignment Appeal Process

**11.5.1 Student/Parent Appeals Stream Assignment**

```
┌────────────────────────────────────────────────────────┐
│ STREAM ASSIGNMENT APPEAL                               │
│ Student: Peter Nkeng (SEC/2024/058)                    │
├────────────────────────────────────────────────────────┤
│                                                        │
│ CURRENT ASSIGNMENT:                                    │
│ • System Suggested: Arts                              │
│ • Admin Assigned: Arts                                │
│                                                        │
│ APPEAL REQUEST:                                        │
│ • Student/Parent requests: Science                    │
│                                                        │
│ REASON FOR APPEAL:                                     │
│ ┌────────────────────────────────────────────────┐   │
│ │ Peter wants to study medicine and feels he     │   │
│ │ can improve in sciences with extra effort.     │   │
│ │ Parents support this decision and willing to   │   │
│ │ provide tutoring.                              │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ ACADEMIC REVIEW:                                       │
│ ──────────────────────────────────────────────        │
│ Year Average: 11.2/20 (Above pass mark ✓)            │
│                                                        │
│ Subject Performance:                                   │
│ • Mathematics: 10.5/20 (Pass)                         │
│ • Physics: 11.0/20 (Pass)                             │
│ • Chemistry: 10.0/20 (Borderline)                     │
│ • Biology: 12.0/20 (Good)                             │
│ • Literature: 14.5/20 (Strong)                        │
│ • History: 13.0/20 (Good)                             │
│                                                        │
│ ANALYSIS:                                              │
│ Student passes all sciences but performance is        │
│ weaker than typical Science stream students.          │
│ However, shows determination and has parental         │
│ support.                                               │
│                                                        │
│ DECISION:                                              │
│ ⚪ APPROVE appeal - Assign to Science                  │
│    (With recommendation for extra tutoring)            │
│                                                        │
│ ⚪ DENY appeal - Keep in Arts                          │
│    (Better suited to strengths)                        │
│                                                        │
│ ⚪ CONDITIONAL approval                                │
│    (Must achieve ≥12 avg in sciences by Term 1)        │
│                                                        │
│ Comments:                                              │
│ ┌────────────────────────────────────────────────┐   │
│ │ Approve appeal with condition: Peter must      │   │
│ │ maintain ≥12 average in all science subjects   │   │
│ │ in Term 1. If not met, will transfer to Arts.  │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ [DENY APPEAL]  [APPROVE WITH CONDITIONS]              │
└────────────────────────────────────────────────────────┘
```

---

### 11.6 Repetition Management

**11.6.1 Student Repeating Same Form**

```
┌────────────────────────────────────────────────────────┐
│ REPETITION: Samuel Tikum (SEC/2024/089)                │
│ Will Repeat: Form 3                                    │
├────────────────────────────────────────────────────────┤
│                                                        │
│ REASON FOR REPETITION:                                 │
│ ──────────────────────────────────────────────        │
│ ❌ Failed Academic Criteria                            │
│ • Year Average: 8.5/20 (Below 10.0)                   │
│ • Failed Subjects: 6 out of 11                        │
│ • Attendance: 65% (Below 85% threshold)               │
│                                                        │
│ DETAILS:                                               │
│ Samuel struggled academically this year and missed    │
│ many classes due to health issues. Repeating Form 3   │
│ will give him opportunity to master fundamentals.     │
│                                                        │
│ ────────────────────────────────────────────          │
│ NEW ENROLLMENT FOR 2025/2026:                          │
│ ────────────────────────────────────────────          │
│ Form: Form 3                                          │
│ Class Section: [Form 3A ▼] (New section assignment)  │
│                                                        │
│ ⚠️ Note: Student will be in different section         │
│ to avoid repeating with exact same classmates.        │
│                                                        │
│ FEE ASSIGNMENT:                                        │
│ Term 1 Fees: 61,000 XAF (Same as Form 3)              │
│                                                        │
│ SUPPORT PLAN:                                          │
│ ☑ Assign remedial classes (Math, Sciences)            │
│ ☑ Weekly progress monitoring                          │
│ ☑ Parental involvement meetings                       │
│                                                        │
│ Parent Notification:                                   │
│ ☑ Email sent with repetition notice                   │
│ ☑ Invitation to counseling session                    │
│                                                        │
│ [CANCEL]  [CONFIRM REPETITION]                        │
└────────────────────────────────────────────────────────┘
```

---

**This completes Part 3 with all Core Operational Modules (Sections 6-11)!**

**Part 3 Summary:**
✅ Section 6: Fee Management Module  
✅ Section 7: Exam & Grading Module  
✅ Section 8: Timetable Management Module  
✅ Section 9: Attendance Tracking Module  
✅ Section 10: Staff Management & Payroll Module  
✅ Section 11: Promotion & Progression Module  

**Next sections to create (Part 4):**
- Section 12: Online Applications Module
- Section 13: Admin/Staff Portal (Complete UI)
- Section 14: Parent/Guardian Portal
- Section 15: Student Portal
- Section 16: Reports & Analytics
- Section 17: Policy Control System
- Section 18: Offline/Online Sync Architecture
- Section 19: EduTrustPay API Integration
- Section 20: UI/UX Design Guidelines
- Section 21: Security & Access Control
- Section 22: Implementation Roadmap

Should I create Part 4 with the remaining sections?
