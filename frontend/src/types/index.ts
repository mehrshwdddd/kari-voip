export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  avatar: string | null
  status: 'active' | 'inactive' | 'suspended'
  branch_id?: number | null
  branch?: {
    id: number
    name: string
    code: string
  } | null
  roles: string[]
  permissions: string[]
  is_2fa_enabled?: boolean
}

export interface Branch {
  id: number
  name: string
  code: string
  phone?: string | null
  address?: string | null
  manager_id?: number | null
  manager?: {
    id: number
    name: string
    email: string
    phone?: string | null
  } | null
  users_count?: number
  contacts_count?: number
  is_active: boolean
  working_hours?: Record<string, unknown> | null
}

export interface Company {
  id: number
  name: string
  phone?: string | null
  email?: string | null
  website?: string | null
  address?: string | null
  national_id?: string | null
  timezone: string
  invoice_prefix: string
  invoice_counter: number
  working_hours?: Record<string, unknown> | null
}

export interface ContactTag {
  id: number
  name: string
  color: string
}

export interface ContactNote {
  id: number
  contact_id: number
  user_id: number
  content: string
  type: 'note' | 'task' | 'follow_up'
  is_pinned: boolean
  due_at?: string | null
  created_at: string
  user?: {
    id: number
    name: string
  }
}

export interface Contact {
  id: number
  branch_id?: number | null
  branch?: {
    id: number
    name: string
  } | null
  first_name: string
  last_name?: string | null
  full_name: string
  national_id?: string | null
  phone_primary: string
  phone_secondary?: string[] | null
  email?: string | null
  gender?: string | null
  birth_date?: string | null
  avatar?: string | null
  status: 'active' | 'vip' | 'blocked' | 'inactive'
  source: 'call' | 'walk_in' | 'referral' | 'campaign' | 'online'
  assigned_user_id?: number | null
  assigned_user?: {
    id: number
    name: string
    phone?: string | null
  } | null
  tag_pivots?: ContactTag[]
  credit_balance: number | string
  loyalty_points: number
  total_debt: number | string
  last_contact_at?: string | null
  notes?: string | null
  notes_list?: ContactNote[]
  calls?: Call[]
  appointments?: Appointment[]
  invoices?: Invoice[]
  accounting_logs?: AccountingLogEntry[]
  is_vip: boolean
  created_at: string
  updated_at: string
}

export interface CallEvent {
  id: number
  call_id: number
  event_type: string
  payload?: Record<string, unknown> | null
  occurred_at: string
}

export interface CallDisposition {
  id: number
  name: string
  color: string
  requires_note: boolean
}

export interface CallQueue {
  id: number
  branch_id?: number | null
  name: string
  strategy: string
  max_wait_seconds: number
  status: string
}

export interface Call {
  id: number
  uuid: string
  branch_id?: number | null
  branch?: { id: number; name: string } | null
  contact_id?: number | null
  contact?: Contact | null
  caller_number: string
  callee_number: string
  direction: 'inbound' | 'outbound'
  type: 'manual' | 'campaign' | 'ivr'
  status: 'ringing' | 'active' | 'on_hold' | 'transferred' | 'ended' | 'missed' | 'failed'
  is_muted: boolean
  is_recording: boolean
  has_recording: boolean
  user_id?: number | null
  user?: { id: number; name: string } | null
  queue_id?: number | null
  queue?: { id: number; name: string } | null
  voip_provider: string
  voip_call_id?: string | null
  voip_channel?: string | null
  started_at: string
  answered_at?: string | null
  ended_at?: string | null
  duration_seconds: number
  ring_duration: number
  disposition?: string | null
  disposition_note?: string | null
  transfer_to?: string | null
  events?: CallEvent[]
}

export interface VoipIntegration {
  id?: number
  name: string
  type: string
  adapter_class: 'asterisk' | 'issabel' | 'sip_webrtc'
  config: {
    host: string
    port: number
    context: string
    reception_mode: 'human_only' | 'ai_only' | 'hybrid'
  }
  status: string
  last_tested_at?: string | null
}

export interface Campaign {
  id: number
  branch_id?: number | null
  branch?: { id: number; name: string } | null
  name: string
  description?: string | null
  type: 'audio' | 'tts' | 'ivr'
  audio_path?: string | null
  tts_text?: string | null
  tts_voice?: string | null
  status: 'draft' | 'scheduled' | 'running' | 'paused' | 'completed' | 'cancelled'
  allowed_hours_start: string
  allowed_hours_end: string
  allowed_days?: string[] | null
  max_concurrent_calls: number
  max_retry_count: number
  retry_interval_minutes?: number[] | null
  dtmf_options?: Record<string, string> | null
  human_transfer_enabled: boolean
  record_calls: boolean
  caller_id: string
  total_contacts: number
  completed_contacts: number
  success_count: number
  failed_count: number
  progress_percentage: number
  created_at: string
  updated_at: string
}

export interface CampaignCallLog {
  id: number
  campaign_id: number
  campaign_contact_id: number
  contact_id?: number | null
  contact?: { id: number; first_name: string; last_name?: string | null } | null
  phone_number: string
  attempt_number: number
  voip_call_id?: string | null
  started_at?: string | null
  answered_at?: string | null
  ended_at?: string | null
  duration_seconds: number
  listen_duration_seconds: number
  listen_percentage: number
  dtmf_input?: string | null
  outcome: string
  agent_transfer_status?: string | null
  retry_count: number
  error_message?: string | null
  created_at: string
}

export interface CampaignReport {
  campaign: Campaign
  summary: {
    total_contacts: number
    completed_contacts: number
    success_count: number
    failed_count: number
    skipped_dnc_count: number
    avg_listen_percentage: number
    avg_duration_seconds: number
    dtmf_interactions: number
    human_transfers: number
  }
  outcomes_chart: Array<{
    outcome: string
    label: string
    count: number
  }>
}

export interface DoNotCallEntry {
  id: number
  phone_number: string
  reason?: string | null
  source: 'manual' | 'dtmf_opt_out' | 'complaint' | 'import'
  added_at: string
  creator?: { id: number; name: string } | null
}

export interface ServiceItem {
  id: number
  branch_id?: number | null
  name: string
  description?: string | null
  duration_minutes: number
  buffer_minutes: number
  price: number | string
  color: string
  is_active: boolean
}

export interface StaffMember {
  id: number
  branch_id?: number | null
  name: string
  title?: string | null
  phone?: string | null
  email?: string | null
  is_active: boolean
}

export interface TimeSlot {
  start_time: string
  end_time: string
  starts_at: string
  available: boolean
  reason?: string | null
}

export interface Appointment {
  id: number
  uuid: string
  branch_id?: number | null
  branch?: { id: number; name: string } | null
  contact_id: number
  contact?: Contact | null
  service_id: number
  service?: ServiceItem | null
  staff_id: number
  staff?: StaffMember | null
  booking_number: string
  status: 'pending' | 'confirmed' | 'completed' | 'cancelled' | 'no_show' | 'rescheduled'
  starts_at: string
  ends_at: string
  duration_minutes: number
  price: number | string
  discount: number | string
  tax: number | string
  total_amount: number | string
  paid_amount: number | string
  payment_status: 'unpaid' | 'partial' | 'paid'
  source: 'call' | 'online' | 'manual' | 'campaign'
  notes?: string | null
}

export interface InvoiceItem {
  id?: number
  name: string
  description?: string | null
  quantity: number
  unit_price: number
  discount_percent?: number
  discount_amount?: number
  tax_percent?: number
  tax_amount?: number
  total_amount?: number
  service_id?: number | null
}

export interface Payment {
  id: number
  uuid: string
  invoice_id: number
  contact_id: number
  amount: number | string
  method: 'cash' | 'card' | 'transfer' | 'cheque' | 'credit' | 'gateway'
  gateway_name?: string | null
  gateway_ref?: string | null
  status: 'pending' | 'completed' | 'failed' | 'refunded'
  paid_at: string
  notes?: string | null
}

export interface Invoice {
  id: number
  uuid: string
  branch_id?: number | null
  branch?: { id: number; name: string } | null
  invoice_number: string
  contact_id: number
  contact?: Contact | null
  status: 'draft' | 'sent' | 'partial' | 'paid' | 'overdue' | 'cancelled' | 'refunded'
  type: 'service' | 'product' | 'campaign' | 'subscription' | 'custom'
  issue_date: string
  due_date?: string | null
  subtotal: number | string
  discount_amount: number | string
  tax_amount: number | string
  total_amount: number | string
  paid_amount: number | string
  remaining_amount: number | string
  currency: string
  notes?: string | null
  items?: InvoiceItem[]
  payments?: Payment[]
  created_at: string
}

export interface AccountingLogEntry {
  id: number
  reference_type: string
  reference_id: number
  contact_id: number
  contact?: { id: number; first_name: string; last_name?: string | null; phone_primary: string } | null
  debit: number | string
  credit: number | string
  balance_after: number | string
  description: string
  created_at: string
}

export interface ReceiptPrinterPayload {
  printer_engine: string
  duration_ms: number
  paper_width_mm: number
  stages: Array<{
    stage: string
    label: string
    progress: number
  }>
  receipt: {
    company_name: string
    company_phone: string
    branch_name: string
    invoice_number: string
    issue_date: string
    status: string
    customer_name: string
    customer_phone: string
    items: Array<{
      name: string
      quantity: number
      unit_price: number
      discount_amount: number
      tax_amount: number
      total_amount: number
    }>
    subtotal: number
    discount_amount: number
    tax_amount: number
    total_amount: number
    paid_amount: number
    remaining_amount: number
    qr_verification: string
  }
}

export interface MessageTemplate {
  id: number
  name: string
  channel: 'sms' | 'whatsapp' | 'email' | 'push'
  category: 'appointment' | 'invoice' | 'campaign' | 'custom'
  subject?: string | null
  body: string
  variables?: string[] | null
  is_active: boolean
}

export interface MessageLog {
  id: number
  contact_id?: number | null
  contact?: { id: number; first_name: string; last_name?: string | null } | null
  channel: string
  to_address: string
  from_address?: string | null
  template_id?: number | null
  template?: { id: number; name: string } | null
  body: string
  status: string
  provider: string
  provider_ref?: string | null
  sent_at?: string | null
  created_at: string
}

export interface FlowNode {
  id: string
  type: string
  position?: { x: number; y: number }
  data: {
    title: string
    prompt?: string
    intent?: string
    intents?: string[]
  }
}

export interface FlowEdge {
  id: string
  source: string
  target: string
  label?: string
}

export interface IvrFlow {
  id: number
  name: string
  description?: string | null
  nodes: FlowNode[]
  edges: FlowEdge[]
  is_active: boolean
  published_at?: string | null
}

export interface CampaignWorkflowItem {
  id: number
  campaign_id?: number | null
  name: string
  nodes: FlowNode[]
  edges: FlowEdge[]
  is_template: boolean
}

export interface AIInteractionSimulation {
  call_id: number
  session_id: number
  intent: string
  intent_label: string
  confidence: number
  entities: Record<string, string>
  reply_text: string
  tts_audio: {
    audio_path: string
    duration_ms: number
    voice: string
  }
  current_node_id: string
  actions_taken: string[]
  summary: {
    summary: string
    sentiment: string
    action_items: string[]
  }
  contact?: Contact
}

export interface ExecutiveReportData {
  kpis: {
    total_contacts: number
    vip_contacts: number
    total_calls: number
    inbound_calls: number
    outbound_calls: number
    missed_calls: number
    avg_call_duration_seconds: number
    total_campaigns: number
    dnc_count: number
    total_appointments: number
    confirmed_appointments: number
    total_invoiced_toman: number
    total_collected_toman: number
    total_outstanding_toman: number
    total_sms_sent: number
  }
  charts: {
    calls_by_direction: Array<{ name: string; count: number }>
    revenue_breakdown: Array<{ name: string; amount: number }>
  }
}

export interface UserPerformanceItem {
  id: number
  name: string
  role: string
  branch: string
  calls_handled: number
  total_talk_seconds: number
  notes_created: number
  invoices_created: number
}

export interface TimelineItem {
  id: string
  type: string
  title: string
  content: string
  author?: string
  created_at: string
  is_pinned?: boolean
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  stats?: {
    total_calls: number
    active_calls: number
    missed_calls: number
    avg_duration: number
  }
}

export interface ApiToken {
  id: number
  name: string
  abilities: string[]
  last_used_at: string | null
  expires_at: string | null
  created_at: string
}

export interface Webhook {
  id: number
  name: string
  url: string
  events: string[]
  is_active: boolean
  retry_count: number
  last_triggered_at: string | null
  last_status_code: number | null
  deliveries_count?: number
  deliveries?: WebhookDelivery[]
  created_at: string
}

export interface WebhookDelivery {
  id: number
  webhook_id: number
  event_type: string
  payload: Record<string, any>
  response_status: number | null
  response_body: string | null
  duration_ms: number | null
  attempt_number: number
  delivered_at: string | null
  failed_at: string | null
  created_at: string
}

export interface AuditLogItem {
  id: number
  user_id: number | null
  user?: { id: number; name: string; email: string }
  event_type: string
  model_type: string
  model_id: number | null
  old_values: Record<string, any> | null
  new_values: Record<string, any> | null
  ip_address: string | null
  user_agent: string | null
  created_at: string
}

export interface SystemStatusData {
  app_name: string
  version: string
  php_version: string
  laravel_version: string
  environment: string
  debug_mode: boolean
  timezone: string
  database: { connection: string; connected: boolean }
  cache: { driver: string }
  queue: { driver: string }
  storage: { writable: boolean; path: string }
  system_healthy: boolean
}

