import React, { useState } from 'react'
import {
  Calendar,
  Plus,
  Clock,
  User,
  CheckCircle2,
  XCircle,
  Receipt,
  Briefcase,
  UserPlus,
  BellRing,
} from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { Badge } from '../../../components/ui/Badge'
import { Modal } from '../../../components/ui/Modal'
import { EmptyState } from '../../../components/ui/EmptyState'
import { InvoiceReceiptPrinter } from '../../../components/billing/InvoiceReceiptPrinter'
import { toPersianDigits } from '../../../lib/numerals'
import { formatToman } from '../../../lib/currency'
import { formatJalali } from '../../../lib/jalali'
import { fa } from '../../../i18n/fa'
import type {
  Appointment,
  ServiceItem,
  StaffMember,
  Contact,
  TimeSlot,
  PaginatedResponse,
} from '../../../types'

export const AppointmentsPage: React.FC = () => {
  const queryClient = useQueryClient()

  const [selectedDate, setSelectedDate] = useState(new Date().toISOString().slice(0, 10))
  const [statusFilter, setStatusFilter] = useState('')
  const [bookModalOpen, setBookModalOpen] = useState(false)
  const [serviceModalOpen, setServiceModalOpen] = useState(false)
  const [staffModalOpen, setStaffModalOpen] = useState(false)
  const [receiptInvoiceId, setReceiptInvoiceId] = useState<number | null>(null)
  const [feedback, setFeedback] = useState<string | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  // Booking form state
  const [contactId, setContactId] = useState('')
  const [serviceId, setServiceId] = useState('')
  const [staffId, setStaffId] = useState('')
  const [bookingDate, setBookingDate] = useState(new Date().toISOString().slice(0, 10))
  const [selectedSlotTime, setSelectedSlotTime] = useState('10:00')
  const [notes, setNotes] = useState('')

  // New Service form state
  const [srvName, setSrvName] = useState('')
  const [srvDuration, setSrvDuration] = useState('30')
  const [srvBuffer, setSrvBuffer] = useState('10')
  const [srvPrice, setSrvPrice] = useState('500000')

  // New Staff form state
  const [stfName, setStfName] = useState('')
  const [stfTitle, setStfTitle] = useState('متخصص و مشاور')
  const [stfPhone, setStfPhone] = useState('')

  const { data: appointmentsData, isLoading } = useQuery<PaginatedResponse<Appointment>>({
    queryKey: ['appointments-list', statusFilter],
    queryFn: async () => {
      const params = new URLSearchParams()
      if (statusFilter) params.append('status', statusFilter)
      const res = await apiClient.get(`/appointments?${params.toString()}`)
      return res.data
    },
  })

  const { data: servicesData } = useQuery<{ services: ServiceItem[] }>({
    queryKey: ['services-list'],
    queryFn: async () => {
      const res = await apiClient.get('/services')
      return res.data
    },
  })

  const { data: staffData } = useQuery<{ staff: StaffMember[] }>({
    queryKey: ['staff-list'],
    queryFn: async () => {
      const res = await apiClient.get('/staff')
      return res.data
    },
  })

  const { data: contactsData } = useQuery<PaginatedResponse<Contact>>({
    queryKey: ['contacts-select'],
    queryFn: async () => {
      const res = await apiClient.get('/contacts?per_page=100')
      return res.data
    },
  })

  const activeStaffId = staffId || String(staffData?.staff?.[0]?.id || '')
  const activeServiceId = serviceId || String(servicesData?.services?.[0]?.id || '')

  // Fetch Available Slots from AvailabilityEngine
  const { data: slotsData } = useQuery<{ slots: TimeSlot[] }>({
    queryKey: ['appointment-slots', activeStaffId, activeServiceId, bookingDate],
    queryFn: async () => {
      const res = await apiClient.get(
        `/appointments/availability?staff_id=${activeStaffId}&service_id=${activeServiceId}&date=${bookingDate}`
      )
      return res.data
    },
    enabled: bookModalOpen && !!activeStaffId && !!activeServiceId && !!bookingDate,
  })

  const bookMutation = useMutation({
    mutationFn: async (payload: any) => {
      const res = await apiClient.post('/appointments', payload)
      return res.data
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['appointments-list'] })
      queryClient.invalidateQueries({ queryKey: ['appointment-slots'] })
      setBookModalOpen(false)
      setFeedback(res.message)
      setTimeout(() => setFeedback(null), 4000)
    },
    onError: (err: any) => {
      setFormError(err.response?.data?.message || fa.common.error)
    },
  })

  const statusMutation = useMutation({
    mutationFn: async ({ id, action }: { id: number; action: 'confirm' | 'complete' | 'no-show' }) => {
      const res = await apiClient.post(`/appointments/${id}/${action}`)
      return res.data
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['appointments-list'] })
      setFeedback(res.message)
      setTimeout(() => setFeedback(null), 3500)
    },
  })

  const issueInvoiceMutation = useMutation({
    mutationFn: async (apt: Appointment) => {
      const res = await apiClient.post('/invoices', {
        contact_id: apt.contact_id,
        appointment_id: apt.id,
        type: 'service',
        items: [
          {
            name: apt.service?.name || 'خدمت نوبت‌دهی',
            description: `نوبت شماره ${apt.booking_number}`,
            quantity: 1,
            unit_price: Number(apt.price) || 500000,
            discount_amount: Number(apt.discount) || 0,
            tax_percent: 9,
            service_id: apt.service_id,
          },
        ],
      })
      return res.data
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['invoices-list'] })
      setReceiptInvoiceId(res.invoice.id)
    },
  })

  const createServiceMutation = useMutation({
    mutationFn: async () => {
      const res = await apiClient.post('/services', {
        name: srvName,
        duration_minutes: parseInt(srvDuration) || 30,
        buffer_minutes: parseInt(srvBuffer) || 10,
        price: parseFloat(srvPrice) || 0,
      })
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['services-list'] })
      setServiceModalOpen(false)
      setSrvName('')
    },
  })

  const createStaffMutation = useMutation({
    mutationFn: async () => {
      const res = await apiClient.post('/staff', {
        name: stfName,
        title: stfTitle,
        phone: stfPhone || null,
      })
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff-list'] })
      setStaffModalOpen(false)
      setStfName('')
    },
  })

  const handleBookSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setFormError(null)
    const targetContactId = contactId || String(contactsData?.data?.[0]?.id || '')
    if (!targetContactId || !activeServiceId || !activeStaffId) {
      setFormError('لطفاً مشتری، خدمت و کارشناس را انتخاب نمایید.')
      return
    }

    bookMutation.mutate({
      contact_id: parseInt(targetContactId),
      service_id: parseInt(activeServiceId),
      staff_id: parseInt(activeStaffId),
      starts_at: `${bookingDate} ${selectedSlotTime}:00`,
      notes: notes || null,
    })
  }

  const appointments = appointmentsData?.data || []
  const slots = slotsData?.slots || []

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-black text-slate-900 dark:text-slate-100">
            {fa.appointments.title}
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {fa.appointments.subtitle}
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            icon={<Briefcase className="h-4 w-4 text-blue-600" />}
            onClick={() => setServiceModalOpen(true)}
          >
            {fa.appointments.addService}
          </Button>
          <Button
            variant="outline"
            size="sm"
            icon={<UserPlus className="h-4 w-4 text-amber-600" />}
            onClick={() => setStaffModalOpen(true)}
          >
            {fa.appointments.addStaff}
          </Button>
          <Button
            size="md"
            icon={<Plus className="h-4 w-4" />}
            onClick={() => {
              setFormError(null)
              setBookModalOpen(true)
            }}
          >
            {fa.appointments.bookNew}
          </Button>
        </div>
      </div>

      {feedback && (
        <div className="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs font-bold text-emerald-700 dark:text-emerald-300">
          {feedback}
        </div>
      )}

      {/* Services & Staff Strip */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card className="p-4 flex items-center justify-between">
          <div>
            <span className="text-xs text-slate-400">تاریخ تقویم جلالی</span>
            <div className="text-sm font-black text-slate-900 dark:text-slate-100 mt-1">
              {formatJalali(selectedDate)}
            </div>
          </div>
          <input
            type="date"
            dir="ltr"
            value={selectedDate}
            onChange={(e) => setSelectedDate(e.target.value)}
            className="rounded-xl border border-slate-200 dark:border-slate-700 px-2.5 py-1.5 text-xs bg-slate-50 dark:bg-slate-800"
          />
        </Card>

        <Card className="p-4 flex items-center justify-between">
          <div>
            <span className="text-xs text-slate-400">خدمات فعال</span>
            <div className="text-sm font-black text-slate-900 dark:text-slate-100 mt-1">
              {toPersianDigits(servicesData?.services?.length ?? 0)} خدمت تعریف‌شده
            </div>
          </div>
          <Badge variant="primary">بررسی خودکار Buffer</Badge>
        </Card>

        <Card className="p-4 flex items-center justify-between">
          <div>
            <span className="text-xs text-slate-400">فیلتر وضعیت نوبت‌ها</span>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="mt-1 block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-transparent text-xs font-bold"
            >
              <option value="">همه نوبت‌ها</option>
              <option value="confirmed">{fa.appointments.statusConfirmed}</option>
              <option value="completed">{fa.appointments.statusCompleted}</option>
              <option value="no_show">{fa.appointments.statusNoShow}</option>
            </select>
          </div>
          <Calendar className="h-6 w-6 text-blue-500" />
        </Card>
      </div>

      {/* Appointments List */}
      {isLoading ? (
        <div className="p-12 text-center text-xs text-slate-400">
          {fa.common.loading}
        </div>
      ) : appointments.length === 0 ? (
        <EmptyState
          icon={<Calendar className="h-10 w-10 text-blue-500 stroke-1" />}
          title="نوبتی ثبت نشده است"
          description="اولین نوبت مشتری را با بررسی هوشمند تداخل زمانی ثبت نمایید."
          actionLabel={fa.appointments.bookNew}
          onAction={() => setBookModalOpen(true)}
        />
      ) : (
        <div className="grid grid-cols-1 gap-4">
          {appointments.map((apt) => (
            <Card key={apt.id} className="p-5">
              <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div className="space-y-1.5">
                  <div className="flex flex-wrap items-center gap-2.5">
                    <span className="font-mono text-xs font-bold px-2.5 py-0.5 rounded-lg bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300">
                      {apt.booking_number}
                    </span>
                    <h3 className="text-sm font-black text-slate-900 dark:text-slate-100">
                      {apt.service?.name || 'خدمت'} — {apt.contact?.first_name}{' '}
                      {apt.contact?.last_name}
                    </h3>
                    <Badge
                      variant={
                        apt.status === 'confirmed'
                          ? 'primary'
                          : apt.status === 'completed'
                          ? 'success'
                          : apt.status === 'no_show' || apt.status === 'cancelled'
                          ? 'danger'
                          : 'warning'
                      }
                    >
                      {apt.status === 'confirmed'
                        ? fa.appointments.statusConfirmed
                        : apt.status === 'completed'
                        ? fa.appointments.statusCompleted
                        : apt.status === 'no_show'
                        ? fa.appointments.statusNoShow
                        : apt.status}
                    </Badge>
                  </div>

                  <div className="flex flex-wrap items-center gap-4 text-xs text-slate-500 pt-1">
                    <span className="flex items-center gap-1 font-bold text-slate-700 dark:text-slate-300">
                      <Clock className="h-3.5 w-3.5 text-blue-500" />
                      {formatJalali(apt.starts_at, true)} ({toPersianDigits(apt.duration_minutes)}{' '}
                      دقیقه)
                    </span>
                    <span className="flex items-center gap-1">
                      <User className="h-3.5 w-3.5 text-amber-500" />
                      کارشناس: {apt.staff?.name || '-'}
                    </span>
                    <span className="font-bold text-emerald-600">
                      مبلغ: {formatToman(apt.total_amount)}
                    </span>
                    <span className="flex items-center gap-1 text-[11px] text-blue-600">
                      <BellRing className="h-3 w-3" />
                      {fa.appointments.remindersScheduled}
                    </span>
                  </div>
                </div>

                {/* Quick Actions */}
                <div className="flex flex-wrap items-center gap-2">
                  {apt.status !== 'completed' && (
                    <Button
                      size="sm"
                      className="bg-emerald-600 hover:bg-emerald-700"
                      icon={<CheckCircle2 className="h-3.5 w-3.5" />}
                      onClick={() =>
                        statusMutation.mutate({ id: apt.id, action: 'complete' })
                      }
                    >
                      {fa.appointments.completeAction}
                    </Button>
                  )}

                  {apt.status === 'confirmed' && (
                    <Button
                      size="sm"
                      variant="outline"
                      icon={<XCircle className="h-3.5 w-3.5 text-rose-500" />}
                      onClick={() =>
                        statusMutation.mutate({ id: apt.id, action: 'no-show' })
                      }
                    >
                      {fa.appointments.noShowAction}
                    </Button>
                  )}

                  <Button
                    size="sm"
                    variant="secondary"
                    icon={<Receipt className="h-3.5 w-3.5 text-blue-600" />}
                    isLoading={issueInvoiceMutation.isPending}
                    onClick={() => issueInvoiceMutation.mutate(apt)}
                  >
                    {fa.appointments.issueInvoiceAction}
                  </Button>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}

      {/* Book Appointment Modal with Live Availability Engine Slots */}
      <Modal
        isOpen={bookModalOpen}
        onClose={() => setBookModalOpen(false)}
        title={fa.appointments.bookNew}
        description="انتخاب خدمت، کارشناس و ساعت آزاد (بررسی خودکار تداخل، زمان تنفس و تعطیلات)"
        maxWidth="lg"
      >
        <form onSubmit={handleBookSubmit} className="space-y-4">
          {formError && (
            <div className="p-3 rounded-xl bg-rose-50 text-xs text-rose-600 border border-rose-200">
              {formError}
            </div>
          )}

          <div>
            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
              {fa.appointments.selectContact}
            </label>
            <select
              value={contactId}
              onChange={(e) => setContactId(e.target.value)}
              className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            >
              {contactsData?.data?.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.full_name} ({toPersianDigits(c.phone_primary)})
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                {fa.appointments.selectService}
              </label>
              <select
                value={activeServiceId}
                onChange={(e) => setServiceId(e.target.value)}
                className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
              >
                {servicesData?.services?.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name} ({toPersianDigits(s.duration_minutes)} دقیقه -{' '}
                    {formatToman(s.price)})
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                {fa.appointments.selectStaff}
              </label>
              <select
                value={activeStaffId}
                onChange={(e) => setStaffId(e.target.value)}
                className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
              >
                {staffData?.staff?.map((st) => (
                  <option key={st.id} value={st.id}>
                    {st.name} ({st.title})
                  </option>
                ))}
              </select>
            </div>
          </div>

          <Input
            label={`${fa.appointments.selectDate} (${formatJalali(bookingDate)})`}
            type="date"
            dir="ltr"
            value={bookingDate}
            onChange={(e) => setBookingDate(e.target.value)}
            required
          />

          {/* Interactive Time Slots from AvailabilityEngine */}
          <div>
            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
              {fa.appointments.availableSlots}:
            </label>
            {slots.length === 0 ? (
              <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 text-xs text-slate-400 text-center">
                {fa.appointments.noSlotsAvailable}
              </div>
            ) : (
              <div className="grid grid-cols-3 sm:grid-cols-4 gap-2 max-h-44 overflow-y-auto p-1">
                {slots.map((slot) => (
                  <button
                    key={slot.start_time}
                    type="button"
                    disabled={!slot.available}
                    title={slot.reason || undefined}
                    onClick={() => setSelectedSlotTime(slot.start_time)}
                    className={`py-2 px-2.5 rounded-xl text-xs font-mono font-bold border transition-all ${
                      !slot.available
                        ? 'bg-slate-100 dark:bg-slate-800/40 text-slate-300 dark:text-slate-600 border-slate-200 dark:border-slate-800 line-through cursor-not-allowed'
                        : selectedSlotTime === slot.start_time
                        ? 'bg-blue-600 text-white border-blue-600 shadow-xs cursor-pointer'
                        : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:border-blue-400 cursor-pointer'
                    }`}
                  >
                    {toPersianDigits(slot.start_time)}
                  </button>
                ))}
              </div>
            )}
          </div>

          <Input
            label="توضیحات نوبت (اختیاری)"
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder="توضیحات پذیرش یا درخواست مشتری..."
          />

          <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
            <Button
              type="button"
              variant="outline"
              onClick={() => setBookModalOpen(false)}
            >
              {fa.common.cancel}
            </Button>
            <Button type="submit" isLoading={bookMutation.isPending}>
              ثبت نهایی نوبت
            </Button>
          </div>
        </form>
      </Modal>

      {/* Add Service Modal */}
      <Modal
        isOpen={serviceModalOpen}
        onClose={() => setServiceModalOpen(false)}
        title={fa.appointments.addService}
      >
        <form
          onSubmit={(e) => {
            e.preventDefault()
            createServiceMutation.mutate()
          }}
          className="space-y-4"
        >
          <Input
            label="عنوان خدمت"
            value={srvName}
            onChange={(e) => setSrvName(e.target.value)}
            placeholder="مثال: ویزیت تخصصی"
            required
          />
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="مدت زمان (دقیقه)"
              type="number"
              dir="ltr"
              value={srvDuration}
              onChange={(e) => setSrvDuration(e.target.value)}
              required
            />
            <Input
              label="زمان تنفس بین نوبت (دقیقه)"
              type="number"
              dir="ltr"
              value={srvBuffer}
              onChange={(e) => setSrvBuffer(e.target.value)}
              required
            />
          </div>
          <Input
            label="تعرفه خدمت (تومان)"
            type="number"
            dir="ltr"
            value={srvPrice}
            onChange={(e) => setSrvPrice(e.target.value)}
            required
          />
          <div className="flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setServiceModalOpen(false)}
            >
              {fa.common.cancel}
            </Button>
            <Button type="submit" isLoading={createServiceMutation.isPending}>
              {fa.common.save}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Add Staff Modal */}
      <Modal
        isOpen={staffModalOpen}
        onClose={() => setStaffModalOpen(false)}
        title={fa.appointments.addStaff}
      >
        <form
          onSubmit={(e) => {
            e.preventDefault()
            createStaffMutation.mutate()
          }}
          className="space-y-4"
        >
          <Input
            label="نام و نام خانوادگی کارشناس / پزشک"
            value={stfName}
            onChange={(e) => setStfName(e.target.value)}
            required
          />
          <Input
            label="سمت / تخصص"
            value={stfTitle}
            onChange={(e) => setStfTitle(e.target.value)}
          />
          <Input
            label="شماره تماس"
            dir="ltr"
            value={stfPhone}
            onChange={(e) => setStfPhone(e.target.value)}
          />
          <div className="flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setStaffModalOpen(false)}
            >
              {fa.common.cancel}
            </Button>
            <Button type="submit" isLoading={createStaffMutation.isPending}>
              {fa.common.save}
            </Button>
          </div>
        </form>
      </Modal>

      {/* dqnamo Receipt Printer Modal when issuing invoice from Appointment */}
      <InvoiceReceiptPrinter
        invoiceId={receiptInvoiceId}
        isOpen={!!receiptInvoiceId}
        onClose={() => setReceiptInvoiceId(null)}
      />
    </div>
  )
}
