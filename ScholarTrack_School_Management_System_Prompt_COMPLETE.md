# ScholarTrack School Management System - COMPLETE Development Prompt

**System Name:** ScholarTrack  
**Target:** Cameroon Anglophone Secondary Schools  
**Deployment:** Single-school instance with offline/online sync  
**Document Version:** 1.0 - Complete Edition

---

## 📚 DOCUMENT STRUCTURE

This complete prompt is organized into comprehensive sections covering every aspect of the system:

**PART 1: Foundation** (Sections 1-3)
- Executive Summary
- System Architecture & Technical Foundation
- Database Schema & Data Models

**PART 2: Core Modules** (Sections 4-11)
- Academic Configuration Module  
- Student Management Module
- Fee Management Module
- Exam & Grading Module
- Timetable Management Module
- Attendance Tracking Module
- Staff Management & Payroll Module
- Promotion & Progression Module

**PART 3: User Portals** (Sections 12-15)
- Online Applications Module
- Admin/Staff Portal
- Parent/Guardian Portal
- Student Portal

**PART 4: System Features** (Sections 16-22)
- Reports & Analytics
- Policy Control System
- Offline/Online Sync Architecture
- EduTrustPay API Integration Points
- UI/UX Design Guidelines
- Security & Access Control
- Implementation Roadmap

---

*Note: Due to the comprehensive nature of this prompt, it has been created in multiple connected parts. Each part is detailed and production-ready.*

**For the complete prompt, please refer to:**
- ScholarTrack_School_Management_System_Prompt_PART1.md (Database schemas and architecture - see earlier conversation)
- ScholarTrack_School_Management_System_Prompt_PART2.md (Academic Config & Student Management - just created)
- Additional parts to follow based on your needs

---

## QUICK START GUIDE FOR DEVELOPERS

### Prerequisites
- Review all parts of this comprehensive prompt
- Understand Cameroon secondary school system (Forms 1-7, GCE system)
- Familiarize yourself with offline-first architecture patterns

### Development Phases

**Phase 1: Core Setup** (Weeks 1-4)
1. Set up database (PostgreSQL + SQLite for offline)
2. Implement authentication & role-based access
3. Build Academic Configuration module
4. Create Student Management module

**Phase 2: Academic Operations** (Weeks 5-8)
5. Fee Management module
6. Exam & Grading system
7. Report card generation
8. Timetable management

**Phase 3: Daily Operations** (Weeks 9-12)
9. Attendance tracking (manual + QR)
10. Staff management
11. Basic payroll

**Phase 4: User Portals** (Weeks 13-16)
12. Admin/Staff portal (responsive UI)
13. Parent portal (read-only + requests)
14. Student portal (read-only)
15. Online applications

**Phase 5: Advanced Features** (Weeks 17-20)
16. Offline/online sync architecture
17. Conflict resolution system
18. Reports & analytics
19. Policy control system

**Phase 6: Integration & Polish** (Weeks 21-24)
20. EduTrustPay API integration
21. Comprehensive testing
22. User training materials
23. Deployment & launch

---

## CRITICAL SUCCESS FACTORS

✅ **Understand the Context**
- This is for REAL Cameroon schools with REAL challenges
- Offline capability is NOT optional - it's essential
- Fee clearance enforcement is critical (students can't get report cards without paying)
- System must handle mid-year changes (residence type, class transfers, etc.)

✅ **User-Centric Design**
- Teachers are busy - make marks entry fast and intuitive
- Parents may have low tech literacy - keep parent portal simple
- Students should easily see their performance
- Admin needs powerful filters and bulk actions

✅ **Data Integrity**
- Offline sync conflicts WILL happen - build robust resolution
- Marks once published should be auditable (track all changes)
- Financial records must be tamper-proof
- Student records must be secure

✅ **Performance**
- System must work fast even with 2,000+ students
- Report card generation for 60 students in a class should take < 2 minutes
- Attendance scanning via QR should be near-instant
- Dashboard must load < 2 seconds

✅ **Scalability**
- Design for single school NOW, but architecture should allow multi-tenant LATER
- Database should handle 10+ years of historical data
- File storage (photos, documents) must be efficient

---

Would you like me to continue creating the remaining detailed sections (6-22), or would you prefer me to:

1. **Create detailed mockups/wireframes** for key screens?
2. **Write specific API endpoint documentation** for EduTrustPay integration?
3. **Develop the offline sync algorithm** in detail?
4. **Create a sample data model** with actual test data?

Let me know how you'd like to proceed!
