@php $tone = ['pending' => 'warning', 'accepted' => 'success', 'tentative' => 'info', 'declined' => 'secondary'][$value] ?? 'secondary'; @endphp
<span class="badge bg-{{ $tone }}-subtle text-{{ $tone }} status-pill">{{ \App\Models\Meetings\Attendee::RESPONSES[$value] ?? ucfirst($value) }}</span>
