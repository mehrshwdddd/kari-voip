import React from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import { LayoutDashboard, Headphones, Calendar, Receipt, Users } from 'lucide-react'
import { MenuDock, type DockItem } from '../devigner/MenuDock'
import { fa } from '../../i18n/fa'

export const MobileBottomDock: React.FC = () => {
  const navigate = useNavigate()
  const location = useLocation()

  const dockItems: DockItem[] = [
    {
      id: 'dashboard',
      label: fa.nav.dashboard,
      icon: <LayoutDashboard className="h-5 w-5" />,
      onClick: () => navigate('/dashboard'),
      isActive: location.pathname === '/dashboard',
    },
    {
      id: 'workspace',
      label: fa.nav.workspace,
      icon: <Headphones className="h-5 w-5" />,
      onClick: () => navigate('/workspace'),
      isActive: location.pathname.startsWith('/workspace'),
    },
    {
      id: 'contacts',
      label: fa.nav.contacts,
      icon: <Users className="h-5 w-5" />,
      onClick: () => navigate('/contacts'),
      isActive: location.pathname.startsWith('/contacts'),
    },
    {
      id: 'appointments',
      label: fa.nav.appointments,
      icon: <Calendar className="h-5 w-5" />,
      onClick: () => navigate('/appointments'),
      isActive: location.pathname.startsWith('/appointments'),
    },
    {
      id: 'invoices',
      label: fa.nav.invoices,
      icon: <Receipt className="h-5 w-5" />,
      onClick: () => navigate('/invoices'),
      isActive: location.pathname.startsWith('/invoices'),
    },
  ]

  return (
    <div className="md:hidden">
      <MenuDock items={dockItems} />
    </div>
  )
}
