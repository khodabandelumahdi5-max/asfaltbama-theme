@php($cls = ['new' => 'badge-info', 'contacted' => 'badge-amber', 'negotiation' => 'badge-amber', 'won' => 'badge-ok', 'lost' => 'badge-bad'][$stage] ?? '')
<span class="badge {{ $cls }}">{{ \App\Models\CrmContact::STAGES[$stage] ?? $stage }}</span>
