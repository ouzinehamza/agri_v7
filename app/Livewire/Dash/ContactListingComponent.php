<?php

namespace App\Livewire\Dash;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use App\Models\Contact;
use App\Models\User;
use App\Models\CustomerGroup;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\CreateAction;
use Livewire\Attributes\Url;

class ContactListingComponent extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public $business_id, $contacts = [];

    #[Url(keep: true)]
    public ?string $type = 'both';

    public $types, $reward_enabled, $users, $customer_groups;

    public function mount()
    {
        $this->business_id = request()->session()->get('user.business_id');

        $this->types = ['supplier', 'customer', 'both'];

        if (empty($this->type) || !in_array($this->type, $this->types)) {
            $this->type = 'both';
            return redirect()->route('contacts.index', ['type' => $this->type]);
        }

        if ($this->type == 'supplier') {
            $this->contacts = Contact::where('type', 'supplier')->get();
        } elseif ($this->type == 'customer') {
            $this->contacts = Contact::where('type', 'customer')->get();
        } elseif ($this->type == 'both') {
            $this->contacts = Contact::whereIn('type', ['supplier', 'customer'])->get();
        } else {
            abort(404, 'Not Found');
        }

        $this->users = User::forDropdown($this->business_id);

        $this->customer_groups = [];
        if ($this->type == 'customer') {
            $this->customer_groups = CustomerGroup::forDropdown($this->business_id);
        }
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    //business_id	type	supplier_business_name	name	tax_number	city	state	country	landmark	mobile	landline	alternate_number	pay_term_number	pay_term_type	created_by	is_default
                    ->form([
                        \Filament\Forms\Components\Grid::make([
                            'default' => 2,
                            'sm' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                \Filament\Forms\Components\Select::make('type')
                                    ->options([
                                        'customer' => 'Customer',
                                        'supplier' => 'Supplier',
                                        'both' => 'Both',
                                    ])->default($this->type),
                                \Filament\Forms\Components\Select::make('contact_type')
                                    ->options([
                                        'individual' => 'Individual',
                                        'business' => 'Business',
                                    ]),
                                \Filament\Forms\Components\TextInput::make('name'),
                                \Filament\Forms\Components\TextInput::make('supplier_business_name'),
                                \Filament\Forms\Components\TextInput::make('tax_number'),
                                \Filament\Forms\Components\TextInput::make('city'),
                                \Filament\Forms\Components\TextInput::make('state'),
                                \Filament\Forms\Components\TextInput::make('country'),
                                \Filament\Forms\Components\TextInput::make('landmark'),
                                \Filament\Forms\Components\TextInput::make('mobile'),
                                \Filament\Forms\Components\TextInput::make('landline'),
                                \Filament\Forms\Components\TextInput::make('alternate_number'),
                                \Filament\Forms\Components\TextInput::make('pay_term_number'),
                                \Filament\Forms\Components\TextInput::make('pay_term_type'),
                                \Filament\Forms\Components\Toggle::make('is_default'),
                            ]),
                    ])->slideOver(),
            ])
            ->query(Contact::query()->whereIn('type', $this->type == 'both' ? ['supplier', 'customer'] : [$this->type]))
            ->columns([
                TextColumn::make('name'),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                // ...
            ])
            ->bulkActions([
                // ...
            ]);
    }

    public function render(): View
    {
        return view('livewire.dash.contact-listing-component');
    }
}
