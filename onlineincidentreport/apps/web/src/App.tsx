import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';

// Layouts
import { GuardLayout } from './layouts/GuardLayout';
import { AdminLayout } from './layouts/AdminLayout';

// Guard Realm Pages
import { GuardEntry } from './pages/guard/GuardEntry';
import { IncidentForm } from './pages/guard/IncidentForm';
import { SubmitSuccess } from './pages/guard/SubmitSuccess';

// Super Admin Realm Pages
import { AdminLogin } from './pages/admin/AdminLogin';
import { ChangePasswordModal } from './pages/admin/ChangePasswordModal';
import { DashboardHome } from './pages/admin/DashboardHome';
import { IncidentsList } from './pages/admin/IncidentsList';
import { DailyReportPage } from './pages/admin/DailyReportPage';
import { SitesPage } from './pages/admin/SitesPage';
import { StaffPage } from './pages/admin/StaffPage';
import { SettingsPage } from './pages/admin/SettingsPage';
import { AuditLogsPage } from './pages/admin/AuditLogsPage';
import { BackupsPage } from './pages/admin/BackupsPage';

export const App: React.FC = () => {
  return (
    <BrowserRouter>
      <Routes>
        {/* Default route -> Guard patrol entry */}
        <Route path="/" element={<Navigate to="/g" replace />} />

        {/* ========================================= */}
        {/* GUARD REALM (Mobile-First /g/*)           */}
        {/* ========================================= */}
        <Route path="/g" element={<GuardEntry />} />
        <Route path="/g/:token" element={<GuardEntry />} />
        <Route path="/g/success" element={<SubmitSuccess />} />

        <Route element={<GuardLayout />}>
          <Route path="/g/submit" element={<IncidentForm />} />
        </Route>

        {/* ========================================= */}
        {/* SUPER ADMIN REALM (/admin/*)              */}
        {/* ========================================= */}
        <Route path="/admin/login" element={<AdminLogin />} />
        <Route path="/admin/change-password" element={<ChangePasswordModal />} />

        <Route element={<AdminLayout />}>
          <Route path="/admin" element={<DashboardHome />} />
          <Route path="/admin/incidents" element={<IncidentsList />} />
          <Route path="/admin/daily-report" element={<DailyReportPage />} />
          <Route path="/admin/sites" element={<SitesPage />} />
          <Route path="/admin/staff" element={<StaffPage />} />
          <Route path="/admin/settings" element={<SettingsPage />} />
          <Route path="/admin/audit" element={<AuditLogsPage />} />
          <Route path="/admin/backups" element={<BackupsPage />} />
        </Route>

        {/* Catch-all fallback */}
        <Route path="*" element={<Navigate to="/g" replace />} />
      </Routes>
    </BrowserRouter>
  );
};

export default App;
