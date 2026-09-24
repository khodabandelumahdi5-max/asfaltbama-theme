<?php

namespace App\Http\Controllers\Panel;

use App\Models\CrmActivity;
use App\Models\CrmContact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrmController extends SupplierController
{
    /** Kanban view of the sales pipeline. */
    public function index(Request $request)
    {
        $contacts = $this->company()->contacts()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('phone', 'like', '%'.$request->string('q').'%')
                ->orWhere('organization', 'like', '%'.$request->string('q').'%')))
            ->withCount(['activities as open_tasks_count' => fn ($q) => $q->whereNull('done_at')->whereNotNull('due_at')])
            ->latest('updated_at')
            ->get()
            ->groupBy('stage');

        return view('panel.crm.index', ['columns' => $contacts, 'stages' => CrmContact::STAGES]);
    }

    public function tasks()
    {
        $tasks = CrmActivity::with('contact')
            ->whereHas('contact', fn ($q) => $q->where('company_id', $this->company()->id))
            ->whereNotNull('due_at')
            ->orderByRaw('done_at is not null, due_at asc')
            ->paginate(30);

        return view('panel.crm.tasks', compact('tasks'));
    }

    public function create()
    {
        return view('panel.crm.form', ['contact' => new CrmContact(['stage' => 'new'])]);
    }

    public function store(Request $request)
    {
        $contact = $this->company()->contacts()->create($this->validated($request) + ['source' => 'manual']);

        return redirect()->route('panel.crm.show', $contact)->with('status', 'مخاطب اضافه شد.');
    }

    public function show(CrmContact $contact)
    {
        $this->authorizeOwned($contact);
        $contact->load('activities.user', 'inquiries.product');

        return view('panel.crm.show', ['contact' => $contact]);
    }

    public function update(Request $request, CrmContact $contact)
    {
        $this->authorizeOwned($contact);
        $contact->update($this->validated($request));

        return back()->with('status', 'اطلاعات مخاطب ذخیره شد.');
    }

    public function stage(Request $request, CrmContact $contact)
    {
        $this->authorizeOwned($contact);
        $data = $request->validate(['stage' => ['required', Rule::in(array_keys(CrmContact::STAGES))]]);

        if ($data['stage'] !== $contact->stage) {
            $contact->activities()->create([
                'user_id' => $request->user()->id,
                'type' => 'note',
                'body' => 'مرحله از «'.CrmContact::STAGES[$contact->stage].'» به «'.CrmContact::STAGES[$data['stage']].'» تغییر کرد.',
            ]);
            $contact->update($data);
        }

        return back();
    }

    public function destroy(CrmContact $contact)
    {
        $this->authorizeOwned($contact);
        $contact->delete();

        return redirect()->route('panel.crm.index')->with('status', 'مخاطب حذف شد.');
    }

    public function storeActivity(Request $request, CrmContact $contact)
    {
        $this->authorizeOwned($contact);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(CrmActivity::TYPES))],
            'body' => ['required', 'string', 'max:2000'],
            'due_at' => ['nullable', 'date'],
        ]);

        $contact->activities()->create($data + ['user_id' => $request->user()->id]);
        $contact->touch();

        return back()->with('status', 'فعالیت ثبت شد.');
    }

    public function completeActivity(CrmActivity $activity)
    {
        $this->authorizeOwned($activity->contact);
        $activity->update(['done_at' => $activity->done_at ? null : now()]);

        return back();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'organization' => ['nullable', 'string', 'max:120'],
            'stage' => ['required', Rule::in(array_keys(CrmContact::STAGES))],
            'deal_value' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
