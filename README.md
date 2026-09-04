# BiMAP Admin Panel — XAMPP Setup Guide

## 📁 Installation Steps

### 1. Copy Files
Copy the entire `bimap` folder to:
```
C:\xampp\htdocs\bimap\
```

### 2. Start XAMPP
- Open XAMPP Control Panel
- Start **Apache**
- Start **MySQL**

### 3. Import Database
1. Open your browser → go to http://localhost/phpmyadmin
2. Click **"New"** (top-left) to create a new database
3. Name it: `bimap_db` → Click **Create**
4. Click on `bimap_db` in the left panel
5. Click the **"Import"** tab
6. Click **"Choose File"** → select `bimap_db.sql` from the bimap folder
7. Click **"Go"** (bottom of page)

### 4. Access the Admin Panel
Open your browser and go to:
```
http://localhost/bimap/
```

### 5. Login Credentials
- **Email:** admin@bimap.com
- **Password:** password

---

## 📂 File Structure
```
bimap/
├── index.php                  ← Login page
├── bimap_db.sql               ← Database import file
├── includes/
│   ├── db.php                 ← Database connection
│   ├── admin_header.php       ← Shared sidebar + header
│   └── admin_footer.php       ← Shared footer
└── admin/
    ├── dashboard.php          ← Main dashboard
    ├── complaints.php         ← View & manage complaints
    ├── messages.php           ← Resident feedbacks (Yes/No)
    ├── announcements.php      ← Create & manage announcements
    ├── reports.php            ← Reports & user lists
    ├── settings.php           ← Admin settings
    └── logout.php             ← Logout
```

---

## ✅ Features Included

### Dashboard
- Total / Pending / Resolved complaints count
- Total announcements count
- Recent complaints table
- Recent activity feed
- Quick announcement creation

### Complaints Management
- View all complaints with search & filter
- Filter by status (Pending/Resolved) and Barangay
- View full complaint details in modal
- Mark complaint as Resolved with optional notes
- Pagination

### Feedbacks (Messages)
- View resident Yes/No garbage collection feedbacks
- Filter by collected status
- Collection rate chart by barangay

### Announcements
- Create announcements with title, message, target audience
- Target: All Residents / All Drivers / All Users
- Edit and delete announcements
- Filter by audience

### Reports
- Registered residents list
- Registered drivers list
- Complaints breakdown by barangay

### Settings
- Update admin display name
- Change password

---

## 🔮 Future (Mobile Side)
When you provide the mobile design screenshots for residents and drivers, the following will be added:
- Resident registration & login
- Submit complaint form
- Submit garbage collection feedback (Yes/No)
- View announcements targeted to them
- Driver login, route view, collection log
