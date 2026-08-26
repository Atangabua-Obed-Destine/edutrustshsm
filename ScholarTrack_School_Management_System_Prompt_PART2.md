# ScholarTrack School Management System - Development Prompt (PART 2)

**Continuation from Part 1 - Sections 4-22**

---

## 4. ACADEMIC CONFIGURATION MODULE

### 4.1 Module Overview

This module allows school administrators to set up and manage the complete academic structure of the school. It is the **foundation** upon which all other modules depend.

### 4.2 Sessions (Academic Years) Management

**Purpose:** Define academic years (e.g., 2024/2025, 2025/2026)

**Features:**

**4.2.1 Create New Session**
```
FORM FIELDS:
┌────────────────────────────────────────────────────────┐
│ CREATE NEW ACADEMIC SESSION                           │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Session Name: [2024/2025________]                     │
│               (Format: YYYY/YYYY)                      │
│                                                        │
│ Start Date:   [02/09/2024] 📅                         │
│ End Date:     [30/06/2025] 📅                         │
│                                                        │
│ Status:       ⚪ Upcoming                              │
│               ⚪ Active                                 │
│               ⚪ Past                                   │
│                                                        │
│ ☐ Set as current session                              │
│                                                        │
│ [CANCEL]  [SAVE SESSION]                              │
└────────────────────────────────────────────────────────┘
```

**Validation Rules:**
- Session name must be unique
- End date must be after start date
- Only ONE session can be marked "current" at a time
- Cannot delete session if students are enrolled in it

**4.2.2 Session List View**
```
ACADEMIC SESSIONS
──────────────────────────────────────────────────────────
Session      Start Date   End Date     Status    Actions
──────────────────────────────────────────────────────────
2024/2025    02/09/2024   30/06/2025   Active ✓  [Edit] [View]
2023/2024    04/09/2023   28/06/2024   Past      [View] [Archive]
2025/2026    01/09/2025   29/06/2026   Upcoming  [Edit] [Delete]
──────────────────────────────────────────────────────────
[+ CREATE NEW SESSION]
```

**4.2.3 Session Rollover Process**

At the end of academic year (e.g., June 2025), system prompts:

```
┌────────────────────────────────────────────────────────┐
│ 🎓 ACADEMIC YEAR ROLLOVER                              │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Current session "2024/2025" is ending soon.           │
│                                                        │
│ Would you like to create the next academic year?      │
│                                                        │
│ New Session: [2025/2026________]                       │
│ Start Date:  [01/09/2025] 📅                          │
│ End Date:    [29/06/2026] 📅                          │
│                                                        │
│ This will:                                             │
│ ✓ Create new session 2025/2026                        │
│ ✓ Copy academic structure (forms, subjects)           │
│ ✓ Create terms for new session                        │
│ ✗ NOT auto-promote students (manual process)          │
│ ✗ NOT carry over fees (fees assigned per session)     │
│                                                        │
│ [CANCEL]  [CREATE NEW SESSION]                        │
└────────────────────────────────────────────────────────┘
```

**Important Notes:**
- Creating new session does NOT automatically promote students
- Student promotion is a separate manual process (see Promotion Module)
- Fees must be configured separately for each session

---

### 4.3 Terms Management

**Purpose:** Define terms within each academic session (typically 3 terms per year)

**4.3.1 Create Term**
```
FORM FIELDS:
┌────────────────────────────────────────────────────────┐
│ CREATE TERM                                            │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Session:      [2024/2025 ▼]                           │
│                                                        │
│ Term Number:  ⚪ 1  ⚪ 2  ⚪ 3                          │
│                                                        │
│ Term Name:    [First Term______________]              │
│               (Auto-filled based on number)            │
│                                                        │
│ Start Date:   [02/09/2024] 📅                         │
│ End Date:     [15/12/2024] 📅                         │
│                                                        │
│ ☐ Set as current term                                 │
│                                                        │
│ [CANCEL]  [SAVE TERM]                                 │
└────────────────────────────────────────────────────────┘
```

**4.3.2 Term List View (per Session)**
```
SESSION: 2024/2025

TERMS
──────────────────────────────────────────────────────────
Term          Start Date   End Date     Status    Actions
──────────────────────────────────────────────────────────
First Term    02/09/2024   15/12/2024   Active ✓  [Edit] [Sequences]
Second Term   06/01/2025   28/03/2025   Upcoming  [Edit] [Sequences]
Third Term    07/04/2025   30/06/2025   Upcoming  [Edit] [Delete]
──────────────────────────────────────────────────────────
[+ CREATE TERM]
```

**Validation:**
- Cannot have more than 3 terms per session (configurable in system settings)
- Term dates must fall within session dates
- Terms cannot overlap
- Cannot delete term if sequences/exams exist

---

### 4.4 Sequences (Exams) Management

**Purpose:** Define exam sequences within each term (typically 2 per term, but configurable)

**4.4.1 Create Sequence**
```
FORM FIELDS:
┌────────────────────────────────────────────────────────┐
│ CREATE SEQUENCE                                        │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Session:      [2024/2025 ▼]                           │
│ Term:         [First Term ▼]                          │
│                                                        │
│ Sequence Name:[Sequence 1_______________]             │
│               OR                                       │
│               [Mock Exam________________]             │
│                                                        │
│ Sequence Number: [1__]                                │
│                                                        │
│ Exam Dates:                                            │
│ Start Date:   [04/11/2024] 📅                         │
│ End Date:     [08/11/2024] 📅                         │
│                                                        │
│ Weight in Term Average:                                │
│ [50___]% (For 2 sequences, typically 50% each)        │
│                                                        │
│ Marks Entry Deadline:                                  │
│ [15/11/2024] 📅                                       │
│                                                        │
│ Publication Status:                                    │
│ ⚪ Draft (Teachers can enter marks, not visible)      │
│ ⚪ Published (Marks visible to students/parents)      │
│                                                        │
│ [CANCEL]  [SAVE SEQUENCE]                             │
└────────────────────────────────────────────────────────┘
```

**4.4.2 Sequence List View**
```
TERM: First Term (2024/2025)

SEQUENCES
────────────────────────────────────────────────────────────────
Seq#  Name         Exam Dates      Weight  Published  Actions
────────────────────────────────────────────────────────────────
1     Sequence 1   04-08/11/2024   50%     Yes ✓      [Edit] [Marks Entry]
2     Sequence 2   09-13/12/2024   50%     No         [Edit] [Publish]
────────────────────────────────────────────────────────────────
[+ ADD SEQUENCE]
```

**Special Handling for Mock Exams:**
- Mock exams are treated same as regular sequences
- Just named differently (e.g., "Mock Exam" instead of "Sequence 3")
- Typically added for Form 5 and Upper Sixth students preparing for GCE

**Weight Calculation Example:**
```
Term with 2 sequences:
- Sequence 1: 50%
- Sequence 2: 50%
Total: 100%

Term with 3 sequences (e.g., Form 5 with Mock):
- Sequence 1: 33.33%
- Sequence 2: 33.33%
- Mock Exam: 33.33%
Total: 100%

System auto-calculates term average:
Term Average = (Seq1 × Weight1) + (Seq2 × Weight2) + ...
```

---

### 4.5 Forms Management

**Purpose:** Configure school forms (Form 1 through Upper Sixth)

**4.5.1 Forms are Pre-configured**

The system comes with default forms:
```
FORMS (Pre-loaded)
──────────────────────────────────────────────────────────
Form          Level          Has Streams?  Display Order
──────────────────────────────────────────────────────────
Form 1        First Cycle    No            1
Form 2        First Cycle    No            2
Form 3        First Cycle    No            3
Form 4        Second Cycle   Yes           4
Form 5        Second Cycle   Yes           5
Lower Sixth   A-Level        Yes           6
Upper Sixth   A-Level        Yes           7
──────────────────────────────────────────────────────────
```

**Admin cannot add/delete forms**, but can:
- Enable/disable forms (e.g., if school doesn't offer A-Level)
- Edit form names (rare, but allowed for bilingual schools)

---

### 4.6 Streams Management

**Purpose:** Manage academic streams (Science, Arts, Commercial)

**4.6.1 Stream Configuration**
```
STREAMS
──────────────────────────────────────────────────────────
Stream        Code   Max Subjects  Applicable Forms
──────────────────────────────────────────────────────────
Science       SCI    5             Form 4, 5, Lower 6, Upper 6
Arts          ART    5             Form 4, 5, Lower 6, Upper 6
Commercial    COM    7             Form 4, 5, Lower 6, Upper 6
──────────────────────────────────────────────────────────
[+ ADD CUSTOM STREAM] (Rare, but allowed)
```

**Why Commercial has 7 subjects:**
Commercial students typically take more subjects:
- Core: Accounting, Economics, Commerce, Business Studies, Mathematics, English
- Optional: French or Computer Science
Total: 7 subjects

**4.6.2 Create/Edit Stream**
```
FORM FIELDS:
┌────────────────────────────────────────────────────────┐
│ EDIT STREAM: COMMERCIAL                                │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Stream Name:  [Commercial________________]            │
│ Code:         [COM____]                               │
│                                                        │
│ Maximum Subjects: [7__]                               │
│                                                        │
│ Description:                                           │
│ ┌────────────────────────────────────────────────┐   │
│ │ Business-focused stream with accounting,      │   │
│ │ economics, and commerce subjects.             │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ Applicable to Forms:                                   │
│ ☑ Form 4                                              │
│ ☑ Form 5                                              │
│ ☑ Lower Sixth                                         │
│ ☑ Upper Sixth                                         │
│                                                        │
│ [CANCEL]  [SAVE CHANGES]                              │
└────────────────────────────────────────────────────────┘
```

---

### 4.7 Subjects Management

**Purpose:** Define all subjects taught in school

**4.7.1 Create Subject**
```
FORM FIELDS:
┌────────────────────────────────────────────────────────┐
│ CREATE SUBJECT                                         │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Subject Name: [Mathematics________________]           │
│ Subject Code: [MATH____]                              │
│                                                        │
│ Department:   [Science ▼]                             │
│               (Science, Arts, Languages, Other)        │
│                                                        │
│ Description:                                           │
│ ┌────────────────────────────────────────────────┐   │
│ │ Core mathematics subject covering algebra,    │   │
│ │ geometry, trigonometry, and calculus.         │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ Status:       ⚪ Active  ⚪ Inactive                   │
│                                                        │
│ [CANCEL]  [SAVE SUBJECT]                              │
└────────────────────────────────────────────────────────┘
```

**4.7.2 Subjects List View**
```
SUBJECTS
────────────────────────────────────────────────────────────
Subject          Code   Department  Status    Actions
────────────────────────────────────────────────────────────
Mathematics      MATH   Science     Active    [Edit] [Assign to Forms]
English          ENG    Languages   Active    [Edit] [Assign to Forms]
Physics          PHYS   Science     Active    [Edit] [Assign to Forms]
Chemistry        CHEM   Science     Active    [Edit] [Assign to Forms]
Biology          BIO    Science     Active    [Edit] [Assign to Forms]
Literature       LIT    Arts        Active    [Edit] [Assign to Forms]
History          HIST   Arts        Active    [Edit] [Assign to Forms]
Geography        GEO    Arts        Active    [Edit] [Assign to Forms]
French           FRE    Languages   Active    [Edit] [Assign to Forms]
Accounting       ACC    Commercial  Active    [Edit] [Assign to Forms]
Economics        ECON   Commercial  Active    [Edit] [Assign to Forms]
Commerce         COM    Commercial  Active    [Edit] [Assign to Forms]
Computer Science CS     Science     Active    [Edit] [Assign to Forms]
Religious Studies REL   Other       Active    [Edit] [Assign to Forms]
────────────────────────────────────────────────────────────
[+ CREATE SUBJECT]
```

---

### 4.8 Form-Subject Assignment

**Purpose:** Define which subjects are taught in which forms/streams

**4.8.1 Assign Subjects to Form (First Cycle Example)**

For Forms 1-3 (no streams), all students take same subjects:

```
ASSIGN SUBJECTS: FORM 2
──────────────────────────────────────────────────────────

All students in Form 2 take these subjects:

Selected Subjects                         Coefficient
──────────────────────────────────────────────────────────
☑ Mathematics                             1.0
☑ English Language                        1.0
☑ Physics                                 1.0
☑ Chemistry                               1.0
☑ Biology                                 1.0
☑ Literature in English                   1.0
☑ History                                 1.0
☑ Geography                               1.0
☑ French                                  1.0
☑ Religious Studies                       0.5
☑ Physical Education                      0.5
──────────────────────────────────────────────────────────

Total Subjects: 11

[CANCEL]  [SAVE SUBJECT ASSIGNMENT]
```

**Coefficient Explanation:**
- Most subjects: 1.0 (equal weight)
- Less critical subjects: 0.5 (Religious Studies, PE)
- Used in calculating student overall average

**4.8.2 Assign Subjects to Form with Streams (Second Cycle)**

For Forms 4-5, subjects are stream-specific:

```
ASSIGN SUBJECTS: FORM 4 SCIENCE STREAM
──────────────────────────────────────────────────────────

CORE SUBJECTS (Compulsory)              Coefficient
──────────────────────────────────────────────────────────
☑ Mathematics                           1.5  (Higher weight)
☑ Physics                               1.5
☑ Chemistry                             1.5
☑ Biology                               1.5
☑ English Language                      1.0
──────────────────────────────────────────────────────────

ELECTIVE SUBJECTS (Student chooses up to 5 total)
──────────────────────────────────────────────────────────
☐ Further Mathematics                   1.5
☐ Computer Science                      1.0
☐ Technical Drawing                     1.0
☐ French                                1.0
☐ Religious Studies (Catholic schools)  0.5
──────────────────────────────────────────────────────────

Max Subjects for Science Stream: 5

[CANCEL]  [SAVE SUBJECT ASSIGNMENT]
```

**Important Logic:**
- Core subjects are AUTOMATICALLY assigned to all students in that stream
- Elective subjects are chosen by students during stream assignment
- If student selects Religious Studies as 6th subject (Catholic schools), max becomes 6

**4.8.3 Subject Assignment Matrix View**

Visual overview of all form-subject assignments:

```
SUBJECT ASSIGNMENT MATRIX

Subject         F1  F2  F3  F4-Sci F4-Art F4-Com F5-Sci F5-Art F5-Com L6-Sci L6-Art L6-Com U6-Sci U6-Art U6-Com
────────────────────────────────────────────────────────────────────────────────────────────────────────────────
Mathematics     ✓   ✓   ✓   ✓Core  ✓Core  ✓Core  ✓Core  ✓Core  ✓Core  ✓Core         ✓Core  ✓Core         ✓Core
English         ✓   ✓   ✓   ✓Core  ✓Core  ✓Core  ✓Core  ✓Core  ✓Core  ✓Core  ✓Core  ✓Core  ✓Core  ✓Core  ✓Core
Physics         ✓   ✓   ✓   ✓Core         ✓Elec  ✓Core         ✓Elec  ✓Core                ✓Core
Chemistry       ✓   ✓   ✓   ✓Core         ✓Elec  ✓Core         ✓Elec  ✓Core                ✓Core
Biology         ✓   ✓   ✓   ✓Core  ✓Elec         ✓Core  ✓Elec         ✓Core                ✓Core
Literature      ✓   ✓   ✓          ✓Core                ✓Core                ✓Core                ✓Core
History         ✓   ✓   ✓          ✓Core                ✓Core                ✓Core                ✓Core
Geography       ✓   ✓   ✓          ✓Core                ✓Core                ✓Core                ✓Core
French          ✓   ✓   ✓   ✓Elec  ✓Core  ✓Core  ✓Elec  ✓Core  ✓Core  ✓Elec  ✓Core  ✓Core  ✓Elec  ✓Core  ✓Core
Accounting                                 ✓Core                ✓Core                        ✓Core
Economics                                  ✓Core                ✓Core                        ✓Core
Commerce                                   ✓Core                ✓Core                        ✓Core
Comp Science    ✓   ✓   ✓   ✓Elec         ✓Core  ✓Elec         ✓Core  ✓Elec
Religious St.   ✓   ✓   ✓   ✓Elec  ✓Elec  ✓Elec  ✓Elec  ✓Elec  ✓Elec  ✓Elec  ✓Elec  ✓Elec  ✓Elec  ✓Elec  ✓Elec
────────────────────────────────────────────────────────────────────────────────────────────────────────────────

Legend: ✓ = All students  ✓Core = Compulsory  ✓Elec = Elective

[EDIT ASSIGNMENTS]
```

---

### 4.9 Class Sections Management

**Purpose:** Create actual classes (e.g., Form 3A, Form 4 Science B)

**4.9.1 Create Class Section**
```
FORM FIELDS:
┌────────────────────────────────────────────────────────┐
│ CREATE CLASS SECTION                                   │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Session:      [2024/2025 ▼]                           │
│                                                        │
│ Form:         [Form 4 ▼]                              │
│                                                        │
│ Stream:       [Science ▼]                             │
│               (Only shown if form has streams)         │
│                                                        │
│ Section:      [B____]                                 │
│               (A, B, C, D, or custom)                  │
│                                                        │
│ Full Name:    [Form 4 Science B_____________]         │
│               (Auto-generated, can edit)               │
│                                                        │
│ Class Teacher:[Select Teacher ▼_______________]       │
│                                                        │
│ Primary Room: [Room 15 ▼]                             │
│                                                        │
│ Max Students: [50__]                                  │
│                                                        │
│ [CANCEL]  [SAVE CLASS SECTION]                        │
└────────────────────────────────────────────────────────┘
```

**4.9.2 Class Sections List View**
```
SESSION: 2024/2025

CLASS SECTIONS
────────────────────────────────────────────────────────────────────
Class              Class Teacher   Room      Students   Actions
────────────────────────────────────────────────────────────────────
Form 1A            Mrs. Nkeng      Room 3    48/60     [Edit] [View Students] [Timetable]
Form 1B            Mr. Tabe        Room 4    52/60     [Edit] [View Students] [Timetable]
Form 2A            Mrs. Ayuk       Room 7    45/60     [Edit] [View Students] [Timetable]
Form 3A            Mr. Fon         Room 10   43/60     [Edit] [View Students] [Timetable]
Form 3B            Mrs. Tanyi      Room 11   41/60     [Edit] [View Students] [Timetable]
Form 4 Science A   Mr. Njie        Room 15   28/40     [Edit] [View Students] [Timetable]
Form 4 Science B   Mrs. Akum       Room 16   25/40     [Edit] [View Students] [Timetable]
Form 4 Arts A      Mr. Ashu        Room 18   30/50     [Edit] [View Students] [Timetable]
Form 4 Commercial  Mrs. Fomba      Room 20   22/40     [Edit] [View Students] [Timetable]
Form 5 Science     Mr. Tikum       Lab 1     35/40     [Edit] [View Students] [Timetable]
Lower Sixth Sci    Dr. Mbu         Lab 2     18/30     [Edit] [View Students] [Timetable]
Upper Sixth Sci    Dr. Ngwa        Lab 3     15/30     [Edit] [View Students] [Timetable]
────────────────────────────────────────────────────────────────────
[+ CREATE CLASS SECTION]
```

**Notes on Sections:**
- Sections are organizational (A, B, C) - NOT ability-based
- Students randomly distributed or alphabetically sorted
- Different sections can have same subjects but different timetables
- Joint classes possible (e.g., Form 4 Science A+B take Physics together)

---

### 4.10 Academic Configuration Workflow Summary

**Step-by-step setup for new academic year:**

1. ✅ **Create Session** (e.g., 2024/2025)
2. ✅ **Create Terms** (First, Second, Third)
3. ✅ **Create Sequences** for each term (Seq 1, Seq 2, Mock if needed)
4. ✅ **Configure Subjects** (if not already done)
5. ✅ **Assign Subjects to Forms/Streams**
6. ✅ **Create Class Sections** (Form 3A, Form 4 Science B, etc.)
7. ✅ **Assign Class Teachers** to each section
8. ✅ **Configure Fee Structures** (covered in Fee Management module)
9. ✅ **Create Timetables** (covered in Timetable module)
10. ✅ **Enroll Students** (covered in Student Management module)

**Once setup is complete, the school is ready to operate for the academic year!**

---

## 5. STUDENT MANAGEMENT MODULE

### 5.1 Module Overview

Comprehensive student lifecycle management from enrollment through graduation/withdrawal.

### 5.2 Student Enrollment (Manual - Individual)

**5.2.1 Enrollment Wizard (Multi-Step Process)**

**STEP 1: Personal Information**
```
┌────────────────────────────────────────────────────────┐
│ ENROLL NEW STUDENT - STEP 1/5: PERSONAL INFORMATION   │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Student ID:       [AUTO-GENERATED]                    │
│                   (Generated after enrollment)         │
│                                                        │
│ First Name:       [____________________________]  *   │
│ Middle Name:      [____________________________]      │
│ Last Name:        [____________________________]  *   │
│                                                        │
│ Date of Birth:    [DD/MM/YYYY] 📅  *                  │
│ Gender:           ⚪ Male  ⚪ Female  *                │
│                                                        │
│ Nationality:      [Cameroonian ▼]                     │
│ Place of Birth:   [____________________________]      │
│                                                        │
│ Religion:         [Catholic ▼]                        │
│                   (Catholic, Protestant, Muslim,       │
│                    Other, Prefer not to say)           │
│                                                        │
│ Blood Group:      [Select ▼]                          │
│                   (A+, A-, B+, B-, O+, O-, AB+, AB-,  │
│                    Unknown)                            │
│                                                        │
│ Photo Upload:     [Choose File] 📁                    │
│                   (Max 2MB, JPG/PNG)                   │
│                                                        │
│                               [CANCEL]  [NEXT STEP →] │
└────────────────────────────────────────────────────────┘
```

**STEP 2: Contact Information**
```
┌────────────────────────────────────────────────────────┐
│ ENROLL NEW STUDENT - STEP 2/5: CONTACT INFORMATION    │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Student Email:    [____________________________]      │
│                   (Optional, for older students)       │
│                                                        │
│ Student Phone:    [+237 6XX XXX XXX_________]         │
│                   (Optional)                           │
│                                                        │
│ Home Address:                                          │
│ ┌────────────────────────────────────────────────┐   │
│ │                                                │   │
│ │                                                │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│                         [← BACK]  [CANCEL]  [NEXT →] │
└────────────────────────────────────────────────────────┘
```

**STEP 3: Academic Information**
```
┌────────────────────────────────────────────────────────┐
│ ENROLL NEW STUDENT - STEP 3/5: ACADEMIC INFORMATION   │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Session:          [2024/2025 ▼]  *                    │
│                   (Current session selected)           │
│                                                        │
│ Enrolling in Form:[Form 3 ▼]  *                       │
│                                                        │
│ Stream:           [Not Applicable]                    │
│                   (Only for Forms 4-7)                 │
│                                                        │
│ Class Section:    [Form 3A ▼]  *                      │
│                   (Available sections for Form 3)      │
│                                                        │
│ Residence Type:   ⚪ Day Student  *                    │
│                   ⚪ Boarding Student                  │
│                   ⚪ Half-Boarding Student             │
│                                                        │
│ Enrollment Date:  [02/09/2024] 📅  *                  │
│                                                        │
│ ──────────────────────────────────────────────        │
│ PREVIOUS SCHOOL (If Transfer Student)                 │
│                                                        │
│ Previous School:  [____________________________]      │
│ Previous Class:   [Form 2_____________]               │
│                                                        │
│ Transfer Certificate: [Choose File] 📁                │
│                                                        │
│                         [← BACK]  [CANCEL]  [NEXT →] │
└────────────────────────────────────────────────────────┘
```

**STEP 4: Guardian Information**
```
┌────────────────────────────────────────────────────────┐
│ ENROLL NEW STUDENT - STEP 4/5: GUARDIAN INFORMATION   │
├────────────────────────────────────────────────────────┤
│                                                        │
│ FATHER'S INFORMATION                                   │
│ ─────────────────────────────────────────────         │
│ Full Name:        [____________________________]  *   │
│ Occupation:       [____________________________]      │
│ Phone:            [+237 6XX XXX XXX_________]  *      │
│ Email:            [____________________________]      │
│ Address:          [____________________________]      │
│                                                        │
│ MOTHER'S INFORMATION                                   │
│ ─────────────────────────────────────────────         │
│ Full Name:        [____________________________]  *   │
│ Occupation:       [____________________________]      │
│ Phone:            [+237 6XX XXX XXX_________]  *      │
│ Email:            [____________________________]      │
│ Address:          [____________________________]      │
│                                                        │
│ GUARDIAN (If different from parents)                   │
│ ─────────────────────────────────────────────         │
│ Full Name:        [____________________________]      │
│ Relationship:     [Select ▼]                          │
│ Phone:            [+237 6XX XXX XXX_________]         │
│ Email:            [____________________________]      │
│                                                        │
│ EMERGENCY CONTACT                                      │
│ ─────────────────────────────────────────────         │
│ Name:             [____________________________]  *   │
│ Relationship:     [Select ▼]                          │
│ Phone:            [+237 6XX XXX XXX_________]  *      │
│                                                        │
│ ☐ Create parent portal account                        │
│   (Allow parents to view fees, report cards online)   │
│                                                        │
│   Portal Email:   [____________________________]      │
│                   (Can be different from personal)     │
│                                                        │
│                         [← BACK]  [CANCEL]  [NEXT →] │
└────────────────────────────────────────────────────────┘
```

**STEP 5: Medical Information & Review**
```
┌────────────────────────────────────────────────────────┐
│ ENROLL NEW STUDENT - STEP 5/5: MEDICAL INFO & REVIEW  │
├────────────────────────────────────────────────────────┤
│                                                        │
│ MEDICAL INFORMATION                                    │
│ ─────────────────────────────────────────────         │
│ Known Allergies:                                       │
│ ┌────────────────────────────────────────────────┐   │
│ │ e.g., Peanuts, Penicillin, etc.               │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ Medical Conditions:                                    │
│ ┌────────────────────────────────────────────────┐   │
│ │ e.g., Asthma, Diabetes, etc.                  │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ Special Needs:                                         │
│ ┌────────────────────────────────────────────────┐   │
│ │ e.g., Learning disabilities, physical         │   │
│ │ disabilities, etc.                             │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ Birth Certificate: [Choose File] 📁                   │
│                                                        │
│ ──────────────────────────────────────────────        │
│ ENROLLMENT SUMMARY                                     │
│ ──────────────────────────────────────────────        │
│ Student: John Doe                                      │
│ DOB: 15/03/2011 (Age 13)                              │
│ Class: Form 3A                                         │
│ Residence: Day Student                                 │
│ Guardian: Jane Doe (Mother) - 670123456               │
│                                                        │
│ FEES FOR TERM 1 (Auto-assigned):                       │
│ Tuition Fee:      50,000 XAF                          │
│ Exam Fee:          5,000 XAF                          │
│ Library & ICT:     3,000 XAF                          │
│ Sports & Culture:  2,000 XAF                          │
│ PTA Levy:          1,000 XAF                          │
│ ──────────────────────────────                        │
│ TOTAL:            61,000 XAF                          │
│                                                        │
│ ☑ I confirm all information is correct                │
│                                                        │
│                [← BACK]  [CANCEL]  [ENROLL STUDENT]   │
└────────────────────────────────────────────────────────┘
```

**Post-Enrollment Actions:**
1. Student ID auto-generated (e.g., SEC/2024/045)
2. Student added to Form 3A class roster
3. Subjects auto-assigned (all Form 3 subjects)
4. Fees auto-assigned based on form + residence type
5. Parent portal account created (if selected)
6. Email sent to parent with student ID and fee details

---

### 5.3 Bulk Student Upload

**Purpose:** Upload multiple students at once via Excel/CSV

**5.3.1 Download Template**
```
┌────────────────────────────────────────────────────────┐
│ BULK STUDENT UPLOAD                                    │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Step 1: Download Template                              │
│ ─────────────────────────────────────────────         │
│ Download the Excel or CSV template to ensure your     │
│ data is in the correct format.                         │
│                                                        │
│ [📥 DOWNLOAD EXCEL TEMPLATE (.xlsx)]                  │
│ [📥 DOWNLOAD CSV TEMPLATE (.csv)]                     │
│                                                        │
│ ──────────────────────────────────────────────        │
│                                                        │
│ Step 2: Fill Template                                  │
│ ─────────────────────────────────────────────         │
│ Fill in student details in the downloaded template.   │
│ See sample data in first row for reference.           │
│                                                        │
│ Required Columns: *                                    │
│ - First Name                                           │
│ - Last Name                                            │
│ - Date of Birth (DD/MM/YYYY)                          │
│ - Gender (Male/Female)                                 │
│ - Class Section (e.g., Form 3A)                       │
│ - Residence Type (Day/Boarding/Half-Boarding)         │
│ - Guardian Name                                        │
│ - Guardian Phone                                       │
│                                                        │
│ Optional Columns:                                      │
│ - Middle Name, Email, Religion, Blood Group,          │
│   Home Address, etc.                                   │
│                                                        │
│ ──────────────────────────────────────────────        │
│                                                        │
│ Step 3: Upload Filled Template                         │
│ ─────────────────────────────────────────────         │
│ [Choose File] 📁   No file selected                   │
│                                                        │
│ [CANCEL]  [UPLOAD & VALIDATE]                         │
└────────────────────────────────────────────────────────┘
```

**5.3.2 Template Format (Excel)**
```
| First Name* | Last Name* | Middle | DOB*       | Gender* | Class Section* | Residence Type* | Guardian Name* | Guardian Phone* | Email       | Religion  |
|-------------|------------|--------|------------|---------|----------------|-----------------|----------------|-----------------|-------------|-----------|
| John        | Doe        |        | 15/03/2011 | Male    | Form 3A        | Day             | Jane Doe       | 670123456       | john@ex.com | Catholic  |
| Mary        | Tanyi      | Grace  | 22/07/2011 | Female  | Form 3A        | Boarding        | Peter Tanyi    | 671234567       |             | Catholic  |
| ...         |            |        |            |         |                |                 |                |                 |             |           |
```

**5.3.3 Validation Results**
```
┌────────────────────────────────────────────────────────┐
│ VALIDATION RESULTS                                     │
├────────────────────────────────────────────────────────┤
│                                                        │
│ File: student_upload.xlsx                              │
│ Total Rows: 150                                        │
│                                                        │
│ ✅ PASSED VALIDATION: 142 students                     │
│ ❌ FAILED VALIDATION: 8 students                       │
│                                                        │
│ ──────────────────────────────────────────────        │
│ ERRORS FOUND:                                          │
│ ──────────────────────────────────────────────        │
│ Row 15: Missing required field "Last Name"            │
│ Row 23: Invalid date format "2011-03-15"              │
│         (Use DD/MM/YYYY format)                        │
│ Row 34: Class Section "Form 9A" does not exist        │
│ Row 45: Duplicate guardian phone "670123456"          │
│         (Already used for student in Row 12)           │
│ Row 67: Invalid residence type "Hostel"               │
│         (Must be: Day, Boarding, Half-Boarding)        │
│ Row 89: DOB indicates age 8 (too young for Form 1)   │
│ Row 102: Invalid gender "M" (Must be Male or Female)  │
│ Row 134: Guardian phone invalid format                │
│                                                        │
│ ──────────────────────────────────────────────        │
│                                                        │
│ [DOWNLOAD ERROR REPORT (.xlsx)]                       │
│                                                        │
│ You can fix the errors and re-upload, or proceed      │
│ with importing only the valid students.                │
│                                                        │
│ [RE-UPLOAD]  [CANCEL]  [IMPORT 142 VALID STUDENTS]    │
└────────────────────────────────────────────────────────┘
```

**5.3.4 Import Progress**
```
┌────────────────────────────────────────────────────────┐
│ IMPORTING STUDENTS...                                  │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Progress: 78/142                                       │
│                                                        │
│ ████████████████████░░░░░░░░░░  55%                   │
│                                                        │
│ Current: Creating student Mary Tanyi (Form 3A)        │
│                                                        │
│ Please wait, do not close this window...              │
│                                                        │
└────────────────────────────────────────────────────────┘
```

**5.3.5 Import Success Summary**
```
┌────────────────────────────────────────────────────────┐
│ ✅ IMPORT COMPLETED SUCCESSFULLY!                      │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Total Students Imported: 142                           │
│                                                        │
│ BREAKDOWN BY CLASS:                                    │
│ - Form 1A:  28 students                               │
│ - Form 1B:  25 students                               │
│ - Form 2A:  22 students                               │
│ - Form 3A:  18 students                               │
│ - Form 3B:  17 students                               │
│ - Form 4 Science A: 12 students                       │
│ - Form 4 Arts A: 15 students                          │
│ - Form 5 Science: 5 students                          │
│                                                        │
│ ACTIONS PERFORMED:                                     │
│ ✓ Student records created                             │
│ ✓ Student IDs generated                               │
│ ✓ Subjects auto-assigned                              │
│ ✓ Fees auto-assigned for current term                 │
│ ✓ Guardian records created                            │
│ ✓ Parent portal accounts created (where email given)  │
│                                                        │
│ [VIEW IMPORTED STUDENTS]  [CLOSE]                     │
└────────────────────────────────────────────────────────┘
```

---

### 5.4 Student Profile View

**5.4.1 Main Student Profile**
```
┌─────────────────────────────────────────────────────────────────┐
│ STUDENT PROFILE                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌───────────┐  JOHN DOE                                        │
│ │  [Photo]  │  Student ID: SEC/2024/045                        │
│ │           │  Form 3A | Day Student                           │
│ └───────────┘  Status: Active ✓                                │
│                                                                 │
│ ┌──────────────────────────────────────────────────────────┐  │
│ │ [Overview] [Academic] [Fees] [Payments] [Attendance]    │  │
│ │ [Marks] [Documents] [History] [Edit]                     │  │
│ └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│ ┌─── OVERVIEW TAB ──────────────────────────────────────────┐ │
│ │                                                            │ │
│ │ PERSONAL INFORMATION                                       │ │
│ │ ──────────────────────────────────────────────            │ │
│ │ Full Name:      John Doe                                  │ │
│ │ Date of Birth:  15/03/2011 (Age 13)                       │ │
│ │ Gender:         Male                                       │ │
│ │ Nationality:    Cameroonian                                │ │
│ │ Religion:       Catholic                                   │ │
│ │ Blood Group:    O+                                         │ │
│ │                                                            │ │
│ │ CONTACT INFORMATION                                        │ │
│ │ ──────────────────────────────────────────────            │ │
│ │ Phone:          +237 670 XXX XXX                          │ │
│ │ Email:          john.doe@example.com                       │ │
│ │ Address:        Mile 4 Nkwen, Bamenda                     │ │
│ │                                                            │ │
│ │ ACADEMIC INFORMATION (Current Session: 2024/2025)          │ │
│ │ ──────────────────────────────────────────────            │ │
│ │ Class Section:  Form 3A                                    │ │
│ │ Class Teacher:  Mrs. Ayuk                                  │ │
│ │ Residence Type: Day Student                                │ │
│ │ Enrollment Date: 02/09/2024                                │ │
│ │                                                            │ │
│ │ GUARDIAN INFORMATION                                       │ │
│ │ ──────────────────────────────────────────────            │ │
│ │ Father:         Peter Doe                                  │ │
│ │                 Occupation: Teacher                        │ │
│ │                 Phone: +237 670 123 456                    │ │
│ │                 Email: peter.doe@example.com               │ │
│ │                                                            │ │
│ │ Mother:         Jane Doe                                   │ │
│ │                 Occupation: Nurse                          │ │
│ │                 Phone: +237 671 234 567                    │ │
│ │                 Email: jane.doe@example.com                │ │
│ │                                                            │ │
│ │ Emergency:      Jane Doe (Mother) - 671 234 567           │ │
│ │                                                            │ │
│ │ MEDICAL INFORMATION                                        │ │
│ │ ──────────────────────────────────────────────            │ │
│ │ Allergies:      None reported                              │ │
│ │ Conditions:     None reported                              │ │
│ │ Special Needs:  None                                       │ │
│ │                                                            │ │
│ │ QUICK STATS                                                │ │
│ │ ──────────────────────────────────────────────            │ │
│ │ Current Term Average:   14.5/20 (Term 1)                  │ │
│ │ Class Rank:             8/43                               │ │
│ │ Attendance:             94% (Present 45/48 days)           │ │
│ │ Fee Balance:            12,000 XAF (49,000 paid)           │ │
│ │                                                            │ │
│ └────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ [EDIT STUDENT]  [CHANGE CLASS]  [CHANGE RESIDENCE TYPE]        │
│ [PRINT ID CARD]  [GENERATE TRANSCRIPT]  [DEACTIVATE]           │
└─────────────────────────────────────────────────────────────────┘
```

**5.4.2 Academic Tab**
```
┌─── ACADEMIC TAB ────────────────────────────────────────────┐
│                                                              │
│ CURRENT SESSION: 2024/2025                                   │
│ ──────────────────────────────────────────────              │
│ Class:          Form 3A                                      │
│ Stream:         N/A (General)                                │
│ Class Teacher:  Mrs. Ayuk                                    │
│ Total Students: 43                                           │
│                                                              │
│ SUBJECTS (11 total)                                          │
│ ──────────────────────────────────────────────              │
│ Subject              Teacher        Coefficient              │
│ ──────────────────────────────────────────────              │
│ Mathematics          Mr. Tabe       1.0                      │
│ English Language     Mrs. Nkeng     1.0                      │
│ Physics              Mr. Fon        1.0                      │
│ Chemistry            Mrs. Ayuk      1.0                      │
│ Biology              Mr. Njie       1.0                      │
│ Literature           Mr. Ashu       1.0                      │
│ History              Mrs. Tanyi     1.0                      │
│ Geography            Mr. Tikum      1.0                      │
│ French               Mrs. Akum      1.0                      │
│ Religious Studies    Fr. Mbah       0.5                      │
│ Physical Education   Coach Fomba    0.5                      │
│ ──────────────────────────────────────────────              │
│                                                              │
│ ENROLLMENT HISTORY                                           │
│ ──────────────────────────────────────────────              │
│ Session    Class      Status      Final Avg   Rank          │
│ ──────────────────────────────────────────────              │
│ 2024/2025  Form 3A    Active      In Progress  -            │
│ 2023/2024  Form 2A    Completed   13.2/20      12/45        │
│ 2022/2023  Form 1B    Completed   12.8/20      15/48        │
│ ──────────────────────────────────────────────              │
│                                                              │
│ [VIEW FULL ACADEMIC HISTORY]                                 │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

**5.4.3 Fees Tab**
```
┌─── FEES TAB ────────────────────────────────────────────────┐
│                                                              │
│ CURRENT SESSION: 2024/2025 | TERM: First Term               │
│                                                              │
│ FEE SUMMARY                                                  │
│ ──────────────────────────────────────────────              │
│ Total Assigned:  61,000 XAF                                  │
│ Total Paid:      49,000 XAF                                  │
│ Balance:         12,000 XAF ⚠️                               │
│                                                              │
│ Status:          Partially Paid                              │
│ Fee Clearance:   NOT CLEARED (Cannot collect report card)   │
│                                                              │
│ BREAKDOWN                                                    │
│ ──────────────────────────────────────────────              │
│ Fee Category       Assigned    Paid       Balance   Status  │
│ ──────────────────────────────────────────────              │
│ Tuition Fee        50,000      50,000     0         Paid ✓  │
│ Exam Fee            5,000       0         5,000     Unpaid   │
│ Library & ICT       3,000       0         3,000     Unpaid   │
│ Sports & Culture    2,000       0         2,000     Unpaid   │
│ PTA Levy            1,000       0         1,000     Unpaid   │
│ ──────────────────────────────────────────────              │
│ TOTAL              61,000      49,000    12,000              │
│                                                              │
│ [ASSIGN ADDITIONAL FEE]  [WAIVE FEE]  [VIEW PAYMENT HISTORY]│
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

**5.4.4 Payments Tab**
```
┌─── PAYMENTS TAB ────────────────────────────────────────────┐
│                                                              │
│ PAYMENT HISTORY (All Sessions)                               │
│                                                              │
│ Date       Ref#         Amount    Method      Status  Receipt│
│ ──────────────────────────────────────────────              │
│ 05/10/24   PAY-001234   49,000    MTN MoMo    Verified ✓ [📄]│
│ 15/09/23   PAY-000892   55,000    Bank        Verified ✓ [📄]│
│ 10/01/23   PAY-000456   30,000    Cash        Verified ✓ [📄]│
│ ──────────────────────────────────────────────              │
│                                                              │
│ Total Paid (All Time): 134,000 XAF                          │
│                                                              │
│ [RECORD MANUAL PAYMENT]  [EXPORT PAYMENT STATEMENT]          │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

### 5.5 Student Search & Filters

**5.5.1 Quick Search**
```
┌─────────────────────────────────────────────────────────────┐
│ STUDENT MANAGEMENT                                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ 🔍 Search: [________________] 🔎                            │
│           (Name, Student ID, Phone, Email)                  │
│                                                             │
│ Quick Filters:                                              │
│ ⚪ All Students (1,245)                                     │
│ ⚪ Active (1,189)                                           │
│ ⚪ Graduated (45)                                           │
│ ⚪ Withdrawn (8)                                            │
│ ⚪ Suspended (3)                                            │
│                                                             │
│ [Advanced Filters ▼]                                        │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**5.5.2 Advanced Filters**
```
┌─────────────────────────────────────────────────────────────┐
│ ADVANCED FILTERS                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Session:         [2024/2025 ▼]                             │
│ Form:            [All ▼]                                    │
│ Stream:          [All ▼]                                    │
│ Class Section:   [All ▼]                                    │
│ Residence Type:  [All ▼]                                    │
│ Gender:          [All ▼]                                    │
│                                                             │
│ Fee Status:                                                 │
│ ☐ Fully Paid                                               │
│ ☐ Partially Paid                                           │
│ ☐ Unpaid                                                   │
│ ☐ Overpaid                                                 │
│                                                             │
│ Enrollment Date:                                            │
│ From: [DD/MM/YYYY] To: [DD/MM/YYYY]                        │
│                                                             │
│ [CLEAR FILTERS]  [APPLY FILTERS]                           │
└─────────────────────────────────────────────────────────────┘
```

**5.5.3 Student List (Filtered Results)**
```
STUDENTS (Showing 43 of 1,189)
Filter: Form 3A, Active

┌──┬──────────┬───────────────┬────────┬──────────┬──────────┬──────────┐
│  │ Photo    │ Name          │ ID     │ Residence│ Fee Bal. │ Actions  │
├──┼──────────┼───────────────┼────────┼──────────┼──────────┼──────────┤
│1 │ [👤]     │ Doe, John     │SEC/045 │ Day      │ 12,000   │ [View]   │
│2 │ [👤]     │ Tanyi, Mary   │SEC/046 │ Boarding │ 0 ✓      │ [View]   │
│3 │ [👤]     │ Nkeng, Peter  │SEC/047 │ Day      │ 25,000⚠️ │ [View]   │
│4 │ [👤]     │ Ayuk, Grace   │SEC/048 │ Day      │ 0 ✓      │ [View]   │
│...│          │               │        │          │          │          │
└──┴──────────┴───────────────┴────────┴──────────┴──────────┴──────────┘

Showing 1-25 of 43 | [← Previous] [1] [2] [Next →]

[BULK ACTIONS ▼]  [EXPORT TO EXCEL]  [PRINT CLASS LIST]
```

---

### 5.6 Student Actions & Operations

**5.6.1 Change Class (Mid-Year or Promotion)**
```
┌─────────────────────────────────────────────────────────────┐
│ CHANGE CLASS: John Doe (SEC/2024/045)                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Current Class:   Form 3A (2024/2025)                        │
│                                                             │
│ Reason for Change:                                          │
│ ⚪ Student Promotion (End of year)                          │
│ ⚪ Stream Assignment (Form 3 → Form 4)                      │
│ ⚪ Section Change (Move to different section)               │
│ ⚪ Disciplinary (Move to different class)                   │
│ ⚪ Other                                                     │
│                                                             │
│ New Class:       [Form 4 Science A ▼]                       │
│                                                             │
│ Effective Date:  [01/09/2025] 📅                           │
│                                                             │
│ ⚠️ IMPORTANT NOTES:                                         │
│ • Student's subjects will be updated to match new class    │
│ • Fees will be recalculated based on new form/stream       │
│ • Previous class enrollment will be marked "Completed"     │
│ • This action cannot be undone automatically               │
│                                                             │
│ [CANCEL]  [CONFIRM CLASS CHANGE]                           │
└─────────────────────────────────────────────────────────────┘
```

**5.6.2 Change Residence Type**
```
┌─────────────────────────────────────────────────────────────┐
│ CHANGE RESIDENCE TYPE: John Doe (SEC/2024/045)              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Current Residence: Day Student                              │
│                                                             │
│ New Residence:     ⚪ Day Student                           │
│                    ⚪ Boarding Student                       │
│                    ⚪ Half-Boarding Student                  │
│                                                             │
│ Effective From:    [01/01/2025] 📅                         │
│                    (Start of Second Term)                   │
│                                                             │
│ ⚠️ FEE RECALCULATION:                                       │
│ ──────────────────────────────────────────────             │
│ Current fees (Day Student):    61,000 XAF/term             │
│ New fees (Boarding Student):  116,000 XAF/term             │
│ Additional charge:             55,000 XAF                   │
│                                                             │
│ The additional 55,000 XAF will be added to student's       │
│ Second Term fees.                                           │
│                                                             │
│ ☑ Parent has been notified of fee increase                 │
│                                                             │
│ [CANCEL]  [CONFIRM CHANGE]                                 │
└─────────────────────────────────────────────────────────────┘
```

**5.6.3 Add/Drop Subjects (For Forms 4-7 only)**
```
┌─────────────────────────────────────────────────────────────┐
│ MANAGE SUBJECTS: Mary Tanyi (SEC/2024/046)                  │
│ Form 4 Science A                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ CURRENT SUBJECTS (5/5)                                      │
│ ──────────────────────────────────────────────             │
│ ☑ Mathematics (Core)                                        │
│ ☑ Physics (Core)                                            │
│ ☑ Chemistry (Core)                                          │
│ ☑ Biology (Core)                                            │
│ ☑ English Language (Core)                                   │
│                                                             │
│ AVAILABLE ELECTIVES (Can add up to 2 more)                  │
│ ──────────────────────────────────────────────             │
│ ☐ Further Mathematics                                       │
│ ☐ Computer Science                                          │
│ ☐ Technical Drawing                                         │
│ ☐ French                                                    │
│ ☑ Religious Studies (Catholic) ✓ Selected                  │
│                                                             │
│ Note: Science stream max subjects = 5                       │
│ (6 if Religious Studies added for Catholic schools)         │
│                                                             │
│ [CANCEL]  [SAVE CHANGES]                                   │
└─────────────────────────────────────────────────────────────┘
```

**5.6.4 Withdraw/Deactivate Student**
```
┌─────────────────────────────────────────────────────────────┐
│ ⚠️ WITHDRAW STUDENT: John Doe (SEC/2024/045)                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Reason for Withdrawal:                                      │
│ ⚪ Transfer to Another School                               │
│ ⚪ Financial Difficulties                                   │
│ ⚪ Family Relocation                                        │
│ ⚪ Health Issues                                            │
│ ⚪ Disciplinary                                             │
│ ⚪ Other: [Specify_________________]                        │
│                                                             │
│ Withdrawal Date: [15/11/2024] 📅                           │
│                                                             │
│ OUTSTANDING FEES:                                           │
│ ──────────────────────────────────────────────             │
│ Total Owed:  12,000 XAF                                    │
│                                                             │
│ ⚪ Waive outstanding fees                                   │
│ ⚪ Keep fees owed (block transfer certificate until paid)   │
│                                                             │
│ DOCUMENTS TO ISSUE:                                         │
│ ──────────────────────────────────────────────             │
│ ☑ Transfer Certificate                                     │
│ ☑ Academic Transcript                                      │
│ ☐ Fee Clearance Certificate (if fees cleared)              │
│                                                             │
│ Notes/Comments:                                             │
│ ┌─────────────────────────────────────────────────────┐   │
│ │ Student is transferring to XYZ College in Douala.   │   │
│ │ Parents paid partial fees.                          │   │
│ └─────────────────────────────────────────────────────┘   │
│                                                             │
│ ⚠️ This action will:                                        │
│ • Change student status to "Withdrawn"                     │
│ • Remove student from active class roster                  │
│ • Generate withdrawal documents                            │
│ • Send notification to parents                             │
│                                                             │
│ [CANCEL]  [CONFIRM WITHDRAWAL]                             │
└─────────────────────────────────────────────────────────────┘
```

---

### 5.7 Graduate Student (Form 5 / Upper Sixth)

When student completes final form:

```
┌─────────────────────────────────────────────────────────────┐
│ 🎓 GRADUATE STUDENT: Mary Tanyi (SEC/2020/015)              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Graduating From: Upper Sixth Science                        │
│ Graduation Year:  2024                                      │
│                                                             │
│ Final Results:                                              │
│ ──────────────────────────────────────────────             │
│ Term 1 Average: 15.2/20                                    │
│ Term 2 Average: 15.8/20                                    │
│ Term 3 Average: 16.1/20                                    │
│ Overall Average: 15.7/20                                    │
│ Class Rank: 3/18                                           │
│                                                             │
│ GCE A-Level Results: [Enter Results___________]            │
│ (Optional - external exam results)                          │
│                                                             │
│ Awards/Honors:                                              │
│ ┌─────────────────────────────────────────────────────┐   │
│ │ • Best in Biology                                   │   │
│ │ • 2nd Overall in Class                              │   │
│ └─────────────────────────────────────────────────────┘   │
│                                                             │
│ OUTSTANDING FEES:                                           │
│ ──────────────────────────────────────────────             │
│ Total Owed:  0 XAF ✓ CLEARED                               │
│                                                             │
│ DOCUMENTS TO ISSUE:                                         │
│ ──────────────────────────────────────────────             │
│ ☑ Final Transcript                                         │
│ ☑ Graduation Certificate                                   │
│ ☑ Fee Clearance Certificate                                │
│ ☑ Recommendation Letter (if requested)                     │
│                                                             │
│ POST-GRADUATION INFORMATION:                                │
│ ──────────────────────────────────────────────             │
│ Next Destination: [University ▼]                           │
│ University Name:  [University of Buea____________]         │
│ Program:          [Medicine_______________]                │
│                                                             │
│ ⚠️ This action will:                                        │
│ • Change student status to "Graduated"                     │
│ • Move student to Alumni database                          │
│ • Generate graduation documents                            │
│ • Remove from active student list                          │
│                                                             │
│ [CANCEL]  [CONFIRM GRADUATION]                             │
└─────────────────────────────────────────────────────────────┘
```

---

This completes Section 5 (Student Management Module). The file is getting very long. Should I:

1. Continue adding the remaining sections (6-22) to this same file?
2. Or create a separate Part 3 file for the next sections?

Let me know how you'd like me to proceed!
