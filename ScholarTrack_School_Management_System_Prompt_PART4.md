# ScholarTrack School Management System - Development Prompt (PART 4)

**Condensed Version - Sections 12-22: User Portals, System Features & Technical Specifications**

---

## 📌 CRITICAL CORRECTION - FEE STRUCTURE

**FEES ARE ASSIGNED PER ACADEMIC YEAR, NOT PER TERM**

```
CORRECT FEE STRUCTURE:
──────────────────────────────────────────────────────
Session: 2024/2025
Form: Form 3, Day Student

ANNUAL FEE BREAKDOWN:
• Tuition Fee:        150,000 XAF/year
• Exam Fee:            15,000 XAF/year
• Library & ICT:        9,000 XAF/year
• Sports & Culture:     6,000 XAF/year
• PTA Levy:             3,000 XAF/year
──────────────────────────────────────────────────────
TOTAL ANNUAL FEES:    183,000 XAF

Payment Options:
• Full payment (before Term 1)
• 3 installments (61,000 XAF per term)
• Custom payment plan (with approval)

Fee Clearance:
• Students must clear at least 50% by Term 1 to sit exams
• Students must clear 100% by end of Term 3 for report card/promotion
```

This affects:
- Fee assignment during enrollment (assign annual total)
- Fee structure configuration (annual amounts, not term)
- Payment tracking (track toward annual total)
- Promotion criteria (annual fee clearance required)

---

## 12. ONLINE APPLICATIONS MODULE

### 12.1 Overview
Allow prospective students to apply online before enrollment. Workflow: Application → Review → Entrance Exam (optional) → Accept/Reject → Enrollment.

### 12.2 Key Features

**Application Form (Public Portal):**
- Personal information (name, DOB, gender, photo)
- Guardian information (parent/guardian details)
- Previous school information (if transfer student)
- Document uploads (birth certificate, previous report cards)
- Application fee payment (configurable amount)

**Application Workflow:**
```
1. SUBMITTED → Application received, payment pending
2. PAYMENT VERIFIED → Application under review
3. SHORTLISTED → Invited for entrance exam (if applicable)
4. EXAM COMPLETED → Results being evaluated
5. ACCEPTED → Offer letter sent to parent
6. ENROLLED → Converted to active student
7. REJECTED → Notification sent to parent
```

**Admin Review Interface:**
- View all applications with filters (status, form applying for, date)
- Bulk actions: Accept, Reject, Shortlist
- Schedule entrance exams
- Generate offer letters
- Convert accepted applicants to enrolled students (one-click)

**Entrance Exam Management:**
- Schedule exam dates
- Send exam invitations to shortlisted applicants
- Record exam scores
- Auto-rank applicants by score

**Key Business Rules:**
- Application fee is non-refundable
- Accepted applicants have 14 days to enroll
- Rejected applicants can reapply next session
- Transfer students must provide previous school transfer certificate

---

## 13. ADMIN/STAFF PORTAL (COMPREHENSIVE UI)

### 13.1 Overview
Main management interface for school administrators and staff. Role-based dashboards with access control.

### 13.2 Dashboard Types

**Principal Dashboard:**
- School overview (total students, staff, revenue)
- Pending approvals (marks, fee waivers, applications)
- Alerts (low attendance students, fee defaulters, disciplinary issues)
- Quick actions (approve marks, view reports, send announcements)

**Vice Principal (Academic) Dashboard:**
- Academic performance overview (class averages, pass rates)
- Pending marks approvals
- Exam calendar
- Teacher workload summary

**Bursar Dashboard:**
- Financial overview (total fees collected, outstanding, revenue)
- Payment transactions (recent payments, pending verifications)
- Fee defaulters list
- Financial reports (by class, by term, by payment method)

**Class Teacher Dashboard:**
- Class roster with photos
- Student performance (marks, attendance, conduct)
- Pending tasks (report card remarks, parent meetings)
- Class announcements

**Subject Teacher Dashboard:**
- Classes assigned
- Marks entry status (completed, pending)
- Teaching schedule
- Student performance in subject

**Front Desk Dashboard:**
- Student search (quick lookup by name/ID)
- Fee payment recording
- Report card printing (if fees cleared)
- Visitor management

### 13.3 Key Portal Features

**Navigation:**
- Sidebar menu with role-based options
- Top bar: User profile, notifications, quick search
- Breadcrumbs for deep navigation

**Common Features Across Roles:**
- Student search (global search bar)
- Calendar (events, exams, holidays)
- Notifications (system alerts, messages)
- Reports center (generate and export)
- Help & Documentation

**Mobile Responsiveness:**
- Optimized for tablets (common for teachers)
- Essential features available on mobile
- Responsive tables with horizontal scroll

---

## 14. PARENT/GUARDIAN PORTAL

### 14.1 Overview
Read-only portal for parents to monitor child's academic progress and fees. Limited write actions (requests only).

### 14.2 Key Features

**Dashboard:**
- Child's photo and basic info
- Current session, class, residence type
- Latest term average and rank
- Fee balance (outstanding amount)
- Recent attendance summary
- Quick links (view report card, pay fees, contact school)

**Academic Performance:**
- View all published marks (by term, by sequence)
- Download report cards (PDF)
- View subject-wise performance trends (charts)
- View class rank history

**Fee Management:**
- View fee breakdown (annual fees assigned)
- View payment history (all transactions)
- Outstanding balance
- Download fee statements
- Link to EduTrustPay for online payment

**Attendance:**
- View daily attendance (present/absent/late)
- Attendance percentage
- Absence history with reasons

**Requests:**
- Request residence type change (Day ↔ Boarding)
- Request transcript/certificates
- Report student absence (with reason)
- Request parent-teacher meeting

**Notifications:**
- Marks published alerts
- Fee reminders
- Absence notifications
- School announcements

**Multiple Children:**
- If guardian has multiple children in school, switch between profiles
- Consolidated fee summary for all children

**Access Control:**
- Separate login per guardian (email-based)
- Can only view own child's data
- Cannot edit any data (read-only except requests)

---

## 15. STUDENT PORTAL

### 15.1 Overview
Self-service portal for students to view academic progress, attendance, fees, and timetable. Strictly read-only.

### 15.2 Key Features

**Dashboard:**
- Student photo, ID, class
- Current term average and rank
- Attendance summary (% present)
- Fee balance
- Today's timetable
- Recent marks

**Academic Performance:**
- View published marks (by term/sequence)
- View report cards (published terms only)
- Download report cards (PDF)
- Subject-wise performance charts
- Class rank over time

**Timetable:**
- View weekly timetable
- Daily schedule
- Teacher names and room numbers
- Exam schedule

**Attendance:**
- View own attendance record
- Attendance percentage
- Late arrivals log

**Fees:**
- View annual fees assigned
- View payments made
- Outstanding balance
- Payment history

**Restrictions:**
- Cannot view other students' data
- Cannot edit any information
- Cannot see unpublished marks
- Cannot access report cards if fees not cleared

**Mobile-First Design:**
- Most students access via phone
- Responsive layout
- Fast loading (minimal graphics)
- Offline view for downloaded report cards

---

## 16. REPORTS & ANALYTICS MODULE

### 16.1 Overview
Comprehensive reporting system with 50+ pre-built reports plus custom report builder.

### 16.2 Report Categories

**Academic Reports:**
1. Class Performance Report (by class, term, session)
2. Subject Performance Report (subject-wise analysis)
3. Student Progress Report (individual student over time)
4. Top Performers Report (top 10/20 students)
5. At-Risk Students Report (failing, low attendance)
6. Pass/Fail Rate Report (by class, subject, term)
7. Grade Distribution Report (A, B, C, D, F counts)
8. Promotion Eligibility Report (end-of-year)
9. GCE Results Analysis (O-Level, A-Level)
10. Teacher Performance Report (by subject, pass rates)

**Financial Reports:**
11. Fee Collection Report (by class, term, payment method)
12. Outstanding Fees Report (defaulters list)
13. Payment Transactions Report (all payments in period)
14. Revenue Analysis Report (by fee category, trend over time)
15. Fee Waivers & Discounts Report
16. Sibling Discount Report
17. Payroll Summary Report (staff salaries)
18. Financial Dashboard (charts and KPIs)

**Attendance Reports:**
19. Class Attendance Report (daily, weekly, term)
20. Student Attendance Report (individual)
21. Late Arrivals Report
22. Chronic Absenteeism Report (students <85%)
23. Perfect Attendance Report (100% students)

**Operational Reports:**
24. Student Enrollment Report (new, transfers, withdrawals)
25. Student Demographics Report (gender, age, residence type)
26. Class Size Report (students per class)
27. Staff Directory Report
28. Teacher Workload Report
29. Timetable Utilization Report (room usage)
30. Exam Schedule Report

**Compliance & Audit Reports:**
31. Fee Clearance Certificate
32. Transcript (official student record)
33. Graduation List
34. Audit Log Report (system changes)
35. Data Export (full database backup)

### 16.3 Report Features

**Export Formats:**
- PDF (print-ready)
- Excel (data analysis)
- CSV (raw data)

**Filters:**
- Date range
- Session/Term
- Class/Form
- Subject
- Teacher
- Student (individual)

**Scheduling:**
- Generate reports automatically (daily, weekly, monthly)
- Email reports to stakeholders
- Save report templates for reuse

**Custom Report Builder:**
- Select data fields
- Apply filters
- Group by dimensions
- Add calculations (averages, sums, percentages)
- Save custom reports

---

## 17. POLICY CONTROL SYSTEM

### 17.1 Overview
Configurable business rules engine allowing schools to customize system behavior without code changes.

### 17.2 Configurable Policies

**Academic Policies:**
- Promotion threshold (e.g., minimum 10.0/20 average)
- Pass mark per subject
- Maximum subjects per stream
- Coefficient/weight system for subjects
- Sequence weights in term average calculation
- Grading scale (marks to letter grades)

**Fee Policies:**
- Annual fee structure (not per term)
- Payment installment options
- Late payment penalties (if any)
- Sibling discount percentages
- Fee clearance requirements:
  - % required to sit exams
  - % required to collect report card
  - Full clearance for promotion/graduation
- Refund policy (if student withdraws)

**Attendance Policies:**
- Minimum attendance for promotion (e.g., 85%)
- Late arrival grace period (e.g., 15 minutes)
- Excused absence types (medical, family emergency)
- Notification thresholds (alert parent after X absences)

**Exam Policies:**
- Can students sit exams with fees owing? (Yes/No)
- Minimum days between sequences
- Make-up exam rules
- Marks editing permissions (who can edit after approval?)
- Marks publication delay (auto-publish or manual?)

**Promotion Policies:**
- Criteria weighting (academic 60%, attendance 20%, conduct 20%)
- Stream assignment method (performance-based or student choice?)
- Appeal process (allowed? deadline?)
- Repetition limits (max times to repeat same form)

**Conduct Policies:**
- Disciplinary actions (warning, suspension, expulsion)
- Suspension conditions (does attendance count during suspension?)
- Conduct weight in promotion

### 17.3 Implementation

**Settings Interface:**
```
POLICY CONFIGURATION
────────────────────────────────────────────────

ACADEMIC POLICIES
• Promotion Threshold: [10.0] / 20
• Pass Mark: [10.0] / 20
• Grading Scale: [Standard Cameroon ▼]

FEE POLICIES
• Annual Fees: [Yes ▼] (Per year, not per term)
• Installment Payments: [Allowed ▼]
• Number of Installments: [3] (per term)
• Exam Sitting with Fees Owing: [Blocked ▼]
• Report Card Collection: [Requires 100% clearance ▼]

ATTENDANCE POLICIES
• Minimum for Promotion: [85]%
• Late Grace Period: [15] minutes
• Absence Notification: [After 2 absences ▼]

[SAVE POLICIES]  [RESET TO DEFAULT]
```

Policies stored in database, applied system-wide. Changes take effect immediately.

---

## 18. OFFLINE/ONLINE SYNC ARCHITECTURE

### 18.1 Overview
Hybrid system supporting both online (cloud) and offline (local network) operation with bidirectional sync.

### 18.2 Architecture Design

**Master-Replica Model:**
- **Cloud Database:** Master (source of truth)
- **Local Database:** Replica (on-premise school server)

**Normal Operation:**
- When **internet available:** System operates on cloud, changes sync immediately
- When **internet down:** System operates on local replica, queues changes for sync

**Sync Mechanisms:**
- **Real-time sync:** When online, changes push to cloud instantly
- **Scheduled sync:** Every 30 minutes, local and cloud sync
- **Manual sync:** Admin can trigger manual sync anytime

### 18.3 Sync Conflicts

**Conflict Scenarios:**
```
SCENARIO 1: Same student record edited offline and online
Example:
• Online (09:00 AM): Bursar records 50,000 XAF payment for John
• Offline (09:05 AM): Bursar records 30,000 XAF payment for John (different transaction)
• Sync (10:00 AM): System detects two payments

Resolution: Both payments are valid → merge both (total 80,000 XAF paid)

────────────────────────────────────────────────

SCENARIO 2: Same marks edited in different places
Example:
• Online: Teacher Mr. Tabe enters Math marks for Form 3A
• Offline: Teacher Mrs. Nkeng enters Math marks for Form 3A (substituting)

Resolution: Conflict detected → admin reviews → keep correct one

────────────────────────────────────────────────

SCENARIO 3: Student promoted offline but also promoted online differently
Example:
• Offline: Admin promotes John to Form 4 Science
• Online: Admin promotes John to Form 4 Arts

Resolution: Last-write-wins OR admin manual resolution
```

**Conflict Resolution Dashboard:**
```
SYNC CONFLICTS (3 pending)
────────────────────────────────────────────────
1. Student Marks Conflict
   Student: John Doe (SEC/2024/045)
   Subject: Mathematics
   
   Version A (Online): 14.0 (entered 09:00 by Mr. Tabe)
   Version B (Offline): 15.0 (entered 09:05 by Mrs. Nkeng)
   
   [Keep Version A]  [Keep Version B]  [Merge]

2. Payment Conflict
   Student: Mary Tanyi (SEC/2024/046)
   
   Version A (Online): 50,000 XAF (MTN MoMo)
   Version B (Offline): 50,000 XAF (Cash)
   
   Analysis: Same payment recorded twice
   [Keep Version A]  [Keep Version B]  [Merge as duplicate]

[AUTO-RESOLVE OBVIOUS CONFLICTS]  [EXPORT REPORT]
```

**Auto-Resolution Rules:**
1. **Additions (no conflict):** Student enrolled offline + different student enrolled online = both added
2. **Non-overlapping edits:** Different fields edited = merge both changes
3. **Payments:** Multiple payments = merge (likely different transactions)
4. **Deletions:** Item deleted offline + edited online = flag for review
5. **Timestamp-based:** If same field edited, most recent wins (configurable)

### 18.4 Sync Monitoring

**Sync Status Dashboard:**
```
SYNC STATUS
────────────────────────────────────────────────
Last Successful Sync: 2 minutes ago
Sync Frequency: Every 30 minutes
Connection Status: ✅ Online (Cloud connected)

SYNC STATISTICS (Last 24 hours)
• Successful Syncs: 48/48
• Records Synced: 1,234
• Conflicts Detected: 3 (Resolved: 3)
• Failed Syncs: 0

PENDING CHANGES (Local → Cloud)
• 0 pending uploads

DATA SIZE
• Cloud Database: 2.4 GB
• Local Replica: 2.4 GB (In sync ✓)
```

---

## 19. EDUTRUSTPAY API INTEGRATION

### 19.1 Overview
ScholarTrack integrates with EduTrustPay (multi-school payment platform) to enable online fee payments and sync payment records.

### 19.2 Integration Points

**API Endpoints (ScholarTrack exposes):**

```
POST /api/students/lookup
Purpose: EduTrustPay searches for student by ID to verify before payment
Request: { "student_id": "SEC/2024/045" }
Response: { 
  "student_id": "SEC/2024/045",
  "name": "John Doe",
  "class": "Form 3A",
  "session": "2024/2025",
  "annual_fees": 183000,
  "paid": 100000,
  "balance": 83000
}

────────────────────────────────────────────────

POST /api/payments/webhook
Purpose: EduTrustPay sends payment notification after successful transaction
Request: {
  "payment_id": "PAY-2024-001234",
  "student_id": "SEC/2024/045",
  "amount": 50000,
  "method": "MTN_MOMO",
  "status": "VERIFIED",
  "payer_phone": "+237670123456",
  "payer_name": "Jane Doe",
  "transaction_ref": "MTN-XYZ123",
  "timestamp": "2024-11-18T14:30:00Z"
}
Response: {
  "status": "SUCCESS",
  "message": "Payment recorded successfully"
}

────────────────────────────────────────────────

GET /api/payments/verify/{payment_id}
Purpose: Verify payment status in ScholarTrack
Response: {
  "payment_id": "PAY-2024-001234",
  "student_id": "SEC/2024/045",
  "status": "RECORDED",
  "recorded_at": "2024-11-18T14:30:15Z"
}
```

**API Endpoints (ScholarTrack calls):**

```
POST https://edutrustpay.com/api/v1/init-payment
Purpose: Redirect parent to EduTrustPay for payment
Request: {
  "school_id": "STJOSEPH_BAMENDA",
  "student_id": "SEC/2024/045",
  "amount": 50000,
  "currency": "XAF",
  "callback_url": "https://school.example.com/api/payments/webhook"
}
Response: {
  "payment_url": "https://edutrustpay.com/pay/ABC123",
  "payment_id": "PAY-2024-001234"
}
```

### 19.3 Payment Flow

```
PARENT PAYMENT JOURNEY
────────────────────────────────────────────────
1. Parent logs into Parent Portal
2. Views fee balance: 83,000 XAF outstanding
3. Clicks "Pay Fees Online"
4. ScholarTrack calls EduTrustPay init-payment API
5. Parent redirected to EduTrustPay payment page
6. Parent selects payment method (MTN MoMo, Orange Money, etc.)
7. Parent completes payment
8. EduTrustPay sends webhook to ScholarTrack
9. ScholarTrack records payment automatically
10. Parent sees updated balance in portal (real-time)
11. Email/SMS receipt sent to parent
```

### 19.4 Error Handling

**Payment Webhook Failures:**
- If webhook fails, ScholarTrack logs error
- Admin can manually import payment from EduTrustPay dashboard
- Retry mechanism: 3 attempts with exponential backoff

**Duplicate Payments:**
- Check transaction_ref to avoid recording same payment twice
- If duplicate detected, flag for admin review

---

## 20. UI/UX DESIGN GUIDELINES

### 20.1 Design Principles

**Simplicity First:**
- Clean, uncluttered interfaces
- Essential actions prominently displayed
- Hide advanced features under "More options"

**Mobile-Responsive:**
- All portals work on mobile, tablet, desktop
- Touch-friendly buttons (min 44px tap targets)
- Readable fonts (min 14px on mobile)

**Professional & Trustworthy:**
- School colors (blue + orange or customizable)
- Professional typography (Roboto, Inter, or similar)
- Consistent branding (school logo, colors)

**Accessible:**
- WCAG 2.1 AA compliance
- High contrast text
- Keyboard navigation support
- Screen reader friendly

### 20.2 Component Library

**Buttons:**
- Primary (blue): Main actions (Save, Submit)
- Secondary (gray): Cancel, Back
- Danger (red): Delete, Reject
- Success (green): Approve, Confirm

**Forms:**
- Clear labels above inputs
- Required fields marked with *
- Inline validation (real-time feedback)
- Error messages in red below field

**Tables:**
- Sortable columns (click header)
- Pagination (25, 50, 100 per page)
- Filters (date range, status, etc.)
- Export buttons (PDF, Excel)

**Cards:**
- Dashboard widgets use card layout
- Shadow for depth
- Rounded corners (8px)

**Navigation:**
- Sidebar menu (collapsible on mobile)
- Breadcrumbs for deep navigation
- Top bar with user profile, notifications

**Modals:**
- For confirmations, forms, detailed views
- Overlay darkens background
- Close with X or click outside

**Color Palette (Default):**
```
Primary: #1A5276 (Dark Blue)
Secondary: #E67E22 (Orange)
Success: #27AE60 (Green)
Warning: #F39C12 (Yellow)
Danger: #E74C3C (Red)
Gray: #95A5A6 (Neutral)
Background: #FFFFFF (White)
Text: #2C3E50 (Dark Gray)
```

### 20.3 Key UX Patterns

**Progressive Disclosure:**
- Show basic info first
- "Show more" to expand details
- Multi-step wizards for complex tasks

**Feedback:**
- Loading spinners during operations
- Success messages (green toast)
- Error messages (red alert box)
- Confirmation dialogs before destructive actions

**Search & Filter:**
- Global search bar in header
- Advanced filters as drawer/modal
- Save filter presets

**Bulk Actions:**
- Checkboxes to select multiple items
- Bulk action dropdown (Delete, Export, etc.)

---

## 21. SECURITY & ACCESS CONTROL

### 21.1 Authentication

**User Types:**
- Admin/Staff: Email + password login
- Parents: Email + password (account created during enrollment)
- Students: Student ID + password (or email if provided)

**Security Measures:**
- Passwords hashed (bcrypt, min 8 characters)
- 2FA optional (SMS or email OTP)
- Session timeout after 30 minutes of inactivity
- Account lockout after 5 failed login attempts
- Password reset via email

### 21.2 Authorization (Role-Based Access Control)

**Permission Levels:**
1. **Super Admin:** Full system access (Principal)
2. **Admin:** Most features except critical (VP, Bursar)
3. **Teacher:** Class/subject-specific access
4. **Staff:** Limited access (Front Desk)
5. **Parent:** Read-only (own child data)
6. **Student:** Read-only (own data)

**Enforcement:**
- Every API endpoint checks user role
- Frontend hides unauthorized UI elements
- Backend validates permissions on every request

### 21.3 Data Protection

**Encryption:**
- SSL/TLS for all communications (HTTPS)
- Database encryption at rest
- Sensitive fields (passwords, ID numbers) encrypted

**Privacy:**
- Students cannot view other students' data
- Parents cannot view other children's data
- Teachers cannot view classes not assigned to them
- GDPR-compliant (if applicable): Right to access, delete, export data

**Audit Logs:**
- Track all critical actions (who, what, when)
- Actions logged: Student edits, payment records, marks entry, promotions
- Audit log immutable (cannot be deleted/edited)
- Retention: 7 years

### 21.4 Backup & Disaster Recovery

**Backups:**
- Daily automated backups (cloud + local)
- Weekly full backups
- Monthly archives
- Backup retention: 1 year

**Recovery:**
- Point-in-time recovery (restore to any date)
- Recovery Time Objective (RTO): 4 hours
- Recovery Point Objective (RPO): 24 hours

---

## 22. IMPLEMENTATION ROADMAP

### 22.1 Development Phases (24 Weeks)

**PHASE 1: FOUNDATION (Weeks 1-4)**
- Week 1-2: Project setup, database design, authentication
- Week 3-4: Academic configuration module (sessions, terms, forms, subjects)

**PHASE 2: CORE STUDENT & FEE MANAGEMENT (Weeks 5-8)**
- Week 5-6: Student management (enrollment, profiles, search)
- Week 7-8: Fee management (annual fees, payments, clearance)

**PHASE 3: ACADEMIC OPERATIONS (Weeks 9-12)**
- Week 9-10: Exam & grading (marks entry, approval, report cards)
- Week 11: Timetable management
- Week 12: Attendance tracking

**PHASE 4: STAFF & PROMOTION (Weeks 13-16)**
- Week 13-14: Staff management & basic payroll
- Week 15-16: Promotion & progression module

**PHASE 5: USER PORTALS (Weeks 17-20)**
- Week 17: Admin/Staff portal (all roles)
- Week 18: Parent portal
- Week 19: Student portal
- Week 20: Online applications module

**PHASE 6: ADVANCED FEATURES & LAUNCH (Weeks 21-24)**
- Week 21: Reports & analytics
- Week 22: Offline/online sync + conflict resolution
- Week 23: EduTrustPay integration, security hardening
- Week 24: Testing, training materials, deployment

### 22.2 Technology Stack Recommendations

**Backend:**
- Framework: Laravel (PHP) or Django (Python) or Node.js/Express
- Database: PostgreSQL (cloud) + SQLite (offline replica)
- API: RESTful or GraphQL

**Frontend:**
- Framework: React or Vue.js
- UI Library: Tailwind CSS + shadcn/ui
- PWA for offline support

**Mobile:**
- Responsive web (no native app needed initially)
- Progressive Web App (PWA) for offline access

**Hosting:**
- Cloud: AWS, Google Cloud, or Azure
- Local: Ubuntu server with Docker containers

**Payment Integration:**
- EduTrustPay API (as specified)
- Fallback: Direct MTN MoMo, Orange Money APIs

### 22.3 Testing Strategy

**Unit Tests:**
- Test individual functions (marks calculation, fee allocation, etc.)
- Coverage: 80%+ for critical modules

**Integration Tests:**
- Test API endpoints
- Test database operations
- Test third-party integrations (EduTrustPay)

**User Acceptance Testing (UAT):**
- Pilot school (e.g., St. Joseph's Bamenda)
- 1 month trial with real students/staff
- Collect feedback, fix bugs

**Performance Testing:**
- Load test with 2,000+ students
- Report generation speed
- Sync performance

### 22.4 Training & Documentation

**User Manuals:**
- Admin guide (comprehensive, 50+ pages)
- Teacher guide (focused on marks, attendance)
- Parent guide (portal usage)
- Student guide (portal usage)

**Video Tutorials:**
- 5-10 minute videos for key tasks
- Enrollment, marks entry, fee recording, etc.

**Training Sessions:**
- 2-day training for admins/bursar
- 1-day training for teachers
- 1-hour orientation for students/parents

**Support:**
- Help desk (phone, email, WhatsApp)
- FAQ section
- In-app help tooltips

---

## 23. CONCLUSION & NEXT STEPS

### 23.1 Summary

ScholarTrack is a comprehensive school management system designed specifically for Cameroon Anglophone secondary schools (Forms 1-7). It handles:

✅ Complete academic lifecycle (enrollment → graduation)  
✅ Fee management (annual fees with installment options)  
✅ Exam & grading (internal sequences + GCE results)  
✅ Timetables, attendance, staff management  
✅ Promotion & stream assignment  
✅ Three user portals (Admin, Parent, Student)  
✅ Offline/online sync for reliable operation  
✅ EduTrustPay integration for online payments  

### 23.2 Critical Success Factors

**1. User-Centric Design:**
- Teachers need fast, intuitive marks entry
- Parents need simple, clear fee/performance view
- Admins need powerful filters and bulk actions

**2. Offline Capability:**
- Must work when internet is down (common in Cameroon)
- Sync conflicts resolved intelligently

**3. Fee Enforcement:**
- Students blocked from collecting report cards if fees not cleared
- Automated alerts to parents for fee reminders

**4. Accurate Marks Calculation:**
- Term averages, class ranks, coefficients all correct
- Report cards match official Cameroon format

**5. Scalability:**
- Handle 2,000+ students smoothly
- Fast report generation (<2 minutes for 60-student class)

### 23.3 Pilot Deployment Plan

**Target School:** St. Joseph's Secondary School, Bamenda (or similar)

**Timeline:**
- Month 1-2: Setup and data migration (import existing students)
- Month 3: Training (admin, teachers, staff)
- Month 4: Go-live (Term 1 of new session)
- Month 5-6: Monitor, fix bugs, collect feedback
- Month 7: Expand to additional schools

**Success Metrics:**
- 90%+ user adoption (teachers using system)
- 80%+ fee collection rate via system
- <5 critical bugs per month
- 95%+ uptime

---

## 📦 DELIVERABLES

**Development Team Receives:**

1. ✅ **Complete Database Schema** (Part 1)
2. ✅ **All Module Specifications** (Parts 2-4)
   - Academic configuration
   - Student & fee management
   - Exams, timetables, attendance
   - Staff, payroll, promotion
   - User portals
   - Reports, sync, API integration
3. ✅ **UI/UX Guidelines** (Part 4)
4. ✅ **Security & Access Control Specs** (Part 4)
5. ✅ **24-Week Implementation Roadmap** (Part 4)

**What's NOT Included (Future Enhancements):**
- Mobile native apps (iOS/Android)
- Advanced analytics (ML-based predictions)
- SMS gateway integration (for bulk SMS)
- WhatsApp bot for parents
- Library management module
- Hostel/dormitory management
- School bus tracking
- Canteen/cafeteria management

---

## 🎯 FINAL NOTES FOR DEVELOPERS

**Key Architectural Decisions:**

1. **Fees = Annual (NOT per term)** ← Critical correction
   - Assign total annual fees when student enrolls
   - Track payments toward annual total
   - Allow installment plans (e.g., 3 equal payments)

2. **First Cycle = Forms 1-5, Second Cycle = Lower/Upper Sixth**
   - Streams start at Form 4 (not Form 1-3)

3. **Offline-First Architecture**
   - Local database = primary when internet down
   - Sync to cloud when online
   - Conflict resolution dashboard for admins

4. **Report Cards Match Cameroon Standard**
   - Show Seq1, Seq2, Term Average
   - Class rank, subject position
   - 0-20 grading scale
   - Fee clearance note at bottom

5. **EduTrustPay Integration**
   - Webhook-based payment sync
   - Automatic payment recording
   - Error handling for failed webhooks

**Development Best Practices:**

- ✅ Write comprehensive tests (unit + integration)
- ✅ Document all API endpoints (Swagger/OpenAPI)
- ✅ Use migrations for database changes
- ✅ Follow code style guide (PSR-12 for PHP, PEP 8 for Python, Airbnb for JS)
- ✅ Git workflow: feature branches, code reviews, CI/CD
- ✅ Version control for database schema changes
- ✅ Error logging (Sentry, LogRocket, or similar)
- ✅ Performance monitoring (New Relic, DataDog)

**When in Doubt:**
- Refer to this comprehensive prompt
- Consult with school administrators for clarification
- Test with real users (teachers, parents) early and often

---

**END OF PART 4 - ScholarTrack Development Prompt Complete** ✅

═══════════════════════════════════════════════════════
**TOTAL DOCUMENTATION:**
- Part 1: Foundation & Database (Sections 1-3)
- Part 2: Core Configuration & Students (Sections 4-5)
- Part 3: Operational Modules (Sections 6-11)
- Part 4: Portals, Features & Technical (Sections 12-22)

**COMPLETE AND READY FOR DEVELOPMENT!** 🚀
═══════════════════════════════════════════════════════

---

## 23. DATA MIGRATION & IMPORT MODULE

### 23.1 Overview

Critical module for migrating existing school data into ScholarTrack. Handles:
- **School onboarding** (bulk migration of all data)
- **Individual student transfers** (mid-year transfers)
- **Historical data import** (past academic years)

### 23.2 School Onboarding Migration

**6-Step Wizard Process:**

**Step 1:** School basic information (name, logo, principal, etc.)

**Step 2:** Import current students
- Excel/CSV template with columns: Student ID, Name, DOB, Gender, Form, Class, Stream, Residence Type, Guardian info
- Validation checks: Required fields, data formats, age appropriateness
- Auto-generates Student IDs if not provided
- Support for 500-2,000+ students

**Step 3:** Import staff members
- Template: Staff ID, Name, Position, Subject(s), Department, Salary, Date Joined
- Role assignment during import
- Can start with just Principal + Bursar, add others later

**Step 4:** Import historical academic data (optional)
- Past sessions (2023/2024, 2022/2023, etc.)
- Student marks, report cards, class ranks
- Allows trend analysis and historical reporting

**Step 5:** Import fee payment history
- Past payment transactions
- Auto-calculate current balances
- Link payments to students

**Step 6:** Review summary & execute import
- Shows totals: X students, Y staff, Z marks records
- Progress bar during import (10-15 minutes for 1,000 students)
- Rollback if errors occur

### 23.3 Individual Student Transfer (Mid-Year)

**Scenario:** Peter Nkeng transfers from ABC Secondary School to St. Joseph's in Term 2.

**Transfer Import Wizard:**

```
IMPORT TRANSFER STUDENT
──────────────────────────────────────────────
Student Information:
• Name: Peter Nkeng
• Previous School: ABC Secondary School, Douala
• Previous Form: Form 3
• Transfer Date: 15/01/2025
• Reason: Family relocation

Documents Uploaded:
• Transfer Certificate ✓
• Previous Report Cards (Term 1) ✓
• Birth Certificate ✓

New Enrollment:
• School: St. Joseph's Secondary School
• Session: 2024/2025 (current)
• Form: Form 3 (same level)
• Section: [Form 3B ▼]
• Residence Type: [Day ▼]

Import Previous Marks:
☑ Import Term 1 marks from previous school
  (Will show on report card as "Transfer Credits")

Fee Assignment:
• Prorated annual fees: 122,000 XAF
  (Original 183,000 XAF × 2/3 remaining terms)
• Due date: 30/01/2025

[CANCEL]  [IMPORT STUDENT]
```

**After Import:**
- Student appears in Form 3B roster
- Term 1 marks imported (marked as "Transfer")
- Fees assigned (prorated for remaining terms)
- Parent portal account created
- Student ID generated: SEC/2025/T001 (T = Transfer)

### 23.4 Historical Data Import

**Use Case:** Import alumni records from past 10 years

**Alumni Import Template:**

```csv
Student_ID,Full_Name,Graduation_Year,Final_Form,Stream,Final_Average,GCE_Results,Current_Status,University,Program
SEC/2015/045,John Doe,2020,Upper Sixth,Science,15.2,3A 2B,University,University of Buea,Medicine
SEC/2016/067,Mary Tanyi,2021,Upper Sixth,Arts,16.8,4A 1B,University,University of Yaounde I,Law
```

Imports:
- Graduated students to Alumni database
- Final marks and GCE results
- Post-graduation tracking (university, career)
- Searchable alumni directory

### 23.5 Partial Data Migration

**Scenario:** School has some digital records (Excel), some paper records

**Flexible Import Options:**

**Option 1:** Import students only first
- Get ScholarTrack operational quickly
- Add marks/fees/attendance later manually or via import

**Option 2:** Import current session only
- Skip historical data
- Focus on current academic year
- Historical data can be added later if needed

**Option 3:** Phased import
- Week 1: Current students + staff
- Week 2: Fee structures + current payments
- Week 3: Timetables + class assignments
- Week 4: Historical data (if available)

### 23.6 Data Validation & Error Handling

**Automatic Validations:**

```
VALIDATION RULES
──────────────────────────────────────────────
Student Data:
✓ Student ID unique (no duplicates)
✓ Required fields present (Name, DOB, Gender, Form)
✓ DOB format: DD/MM/YYYY
✓ Age appropriate for form (6-10 for Form 1, 16-20 for Upper Sixth)
✓ Gender: Male or Female
✓ Form exists in system
✓ Section exists for that form
✓ Stream valid for form (only Forms 4-7 have streams)
✓ Residence type: Day, Boarding, or Half-Boarding
✓ Guardian phone: Valid Cameroon format (+237 6XX XXX XXX)

Staff Data:
✓ Staff ID unique
✓ Position exists (Teacher, Admin, Support)
✓ Subject exists (for teachers)
✓ Email unique (if provided)
✓ Salary positive number

Marks Data:
✓ Student exists in system
✓ Subject exists
✓ Mark range 0-20
✓ Session/Term/Sequence exists
✓ No duplicate marks (same student, subject, sequence)

Payment Data:
✓ Student exists
✓ Amount positive
✓ Payment date valid
✓ Payment method recognized
```

**Error Handling:**

```
ERROR RESOLUTION OPTIONS
──────────────────────────────────────────────
When validation fails:

Option A: Fix in Excel and re-upload
• Download error report with row numbers
• Fix errors in Excel
• Re-upload corrected file

Option B: Import valid records, skip errors
• System imports 821 valid students
• 8 error rows skipped
• Fix errors manually later in system

Option C: Auto-fix common errors
• Missing Student ID → System generates
• Missing middle name → Leave blank (OK)
• Extra spaces in names → Auto-trim
• Date format 2011-03-15 → Convert to 15/03/2011
```

### 23.7 Migration Testing & Validation

**Before Going Live:**

**Test Migration (Sandbox Mode):**
```
CREATE TEST MIGRATION
──────────────────────────────────────────────
This creates a test environment with your data
for review before final import.

☑ Use sample data (10% of students)
☑ Import to test database (won't affect production)
☑ Generate sample reports for review
☑ Test parent portal access

After review:
⚪ Everything looks good → Proceed with full migration
⚪ Found issues → Fix data and retry
⚪ Major problems → Request migration assistance

[CREATE TEST MIGRATION]
```

**Post-Migration Verification:**

```
MIGRATION VERIFICATION CHECKLIST
──────────────────────────────────────────────
After import completes:

☑ Student count matches expected (821 students)
☑ Each student has guardian contact
☑ Fee balances calculated correctly
☑ Class rosters complete (all students assigned)
☑ Staff can log in (test 3-5 accounts)
☑ Sample report card generates correctly
☑ Parent portal accessible (test 3-5 accounts)
☑ Marks imported correctly (spot-check 10 students)
☑ No duplicate student IDs
☑ No orphaned records (students without classes)

[RUN AUTO-VERIFICATION]  [GENERATE VERIFICATION REPORT]
```

### 23.8 Migration Support & Assistance

**Built-in Migration Helper:**

```
MIGRATION ASSISTANCE
──────────────────────────────────────────────
Need help with migration?

☑ Watch video tutorial (15 minutes)
☑ Download step-by-step PDF guide
☑ Schedule migration training session (2 hours)
☑ Request migration support:
  • Upload your data files
  • We validate and format for you
  • You review and approve import
  
Cost: Free for first 500 students
      5,000 XAF per additional 100 students

[WATCH TUTORIAL]  [REQUEST SUPPORT]
```

### 23.9 Incremental Migration (Add Data Later)

**After initial migration, admin can always add:**

**Add Historical Session:**
```
ADD PAST ACADEMIC YEAR
──────────────────────────────────────────────
Session: [2022/2023 ▼]
Import data for this session:

☑ Student list (with forms/classes)
☑ Term marks
☑ Report cards
☑ Fee payments
☑ Attendance records

[IMPORT SESSION DATA]
```

**Add Individual Alumni:**
```
ADD ALUMNI RECORD
──────────────────────────────────────────────
Student Name: [John Doe____________]
Graduation Year: [2020 ▼]
Final Form: [Upper Sixth ▼]
Stream: [Science ▼]
GCE Results: [3A 2B_________]

Post-Graduation:
Current Status: [University ▼]
Institution: [University of Buea___]
Program: [Medicine__________]

[SAVE ALUMNI RECORD]
```

### 23.10 Critical Migration Notes

**Fee Migration:**
- Import ANNUAL fees (not per term)
- System calculates balance from total annual fees minus payments
- Prorated fees for mid-year enrollments

**Student ID Generation:**
- If Student ID missing → Auto-generate: SEC/YYYY/NNN
  - SEC = School code
  - YYYY = Year enrolled
  - NNN = Sequential number
- Can customize format in settings

**Data Ownership:**
- Original data files kept as backup
- School can export data anytime (no vendor lock-in)
- Migration reversible (rollback to pre-import state)

**Timeline:**
- Small school (< 500 students): 1 day migration
- Medium school (500-1,500): 2-3 days migration
- Large school (1,500+): 1 week migration

---

**END OF SECTION 23 - DATA MIGRATION MODULE** ✅

