import React from 'react'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AppLayout } from './components/layout/AppLayout'
import { LoginPage } from './features/auth/pages/LoginPage'
import { DashboardPage } from './features/dashboard/pages/DashboardPage'
import { ReceptionistWorkspacePage } from './features/calls/pages/ReceptionistWorkspacePage'
import { CallsHistoryPage } from './features/calls/pages/CallsHistoryPage'
import { CampaignsPage } from './features/campaigns/pages/CampaignsPage'
import { CampaignDetailPage } from './features/campaigns/pages/CampaignDetailPage'
import { AppointmentsPage } from './features/appointments/pages/AppointmentsPage'
import { InvoicesPage } from './features/billing/pages/InvoicesPage'
import { MessagingPage } from './features/messaging/pages/MessagingPage'
import { AIReceptionistPage } from './features/ai-receptionist/pages/AIReceptionistPage'
import { ReportsPage } from './features/reports/pages/ReportsPage'
import { ContactsPage } from './features/contacts/pages/ContactsPage'
import { ContactProfilePage } from './features/contacts/pages/ContactProfilePage'
import { BranchesPage } from './features/branches/pages/BranchesPage'
import { UsersPage } from './features/users/pages/UsersPage'
import { SettingsPage } from './features/settings/pages/SettingsPage'
import { DeveloperPage } from './features/developer/pages/DeveloperPage'
import { SetupWizardPage } from './features/setup/pages/SetupWizardPage'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      retry: 1,
      staleTime: 1000 * 30, // 30 seconds
    },
  },
})

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          {/* Public Authentication & Setup Wizard */}
          <Route path="/login" element={<LoginPage />} />
          <Route path="/setup" element={<SetupWizardPage />} />

          {/* Protected Application Workspace */}
          <Route path="/" element={<AppLayout />}>
            <Route index element={<Navigate to="/dashboard" replace />} />
            <Route path="dashboard" element={<DashboardPage />} />
            <Route path="workspace" element={<ReceptionistWorkspacePage />} />
            <Route path="calls" element={<CallsHistoryPage />} />
            <Route path="campaigns" element={<CampaignsPage />} />
            <Route path="campaigns/:id" element={<CampaignDetailPage />} />
            <Route path="appointments" element={<AppointmentsPage />} />
            <Route path="invoices" element={<InvoicesPage />} />
            <Route path="messaging" element={<MessagingPage />} />
            <Route path="ai-receptionist" element={<AIReceptionistPage />} />
            <Route path="reports" element={<ReportsPage />} />
            <Route path="contacts" element={<ContactsPage />} />
            <Route path="contacts/:id" element={<ContactProfilePage />} />
            <Route path="branches" element={<BranchesPage />} />
            <Route path="users" element={<UsersPage />} />
            <Route path="settings" element={<SettingsPage />} />
            <Route path="developer" element={<DeveloperPage />} />
          </Route>

          {/* Fallback */}
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </BrowserRouter>
    </QueryClientProvider>
  )
}

export default App
