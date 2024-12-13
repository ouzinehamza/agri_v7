<?php

namespace App\Livewire\Dash;

use App\Models\Business;
use App\Models\Currency;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class BusinessListingComponent extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(Business::query())
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('currency.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tax_number_1')
                    ->label('Tax Number')
                    ->searchable(),
                TextColumn::make('owner.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('accounting_method')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                EditAction::make()
                    ->form($this->getFormSchema()),
                DeleteAction::make(),
            ])
            ->bulkActions([])
            ->headerActions([
                CreateAction::make()
                    ->form($this->getFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['owner_id'] = auth()->id();
                        return $data;
                    }),
            ]);
    }

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Select::make('currency_id')
                ->relationship('currency', 'currency')
                ->required(),
            DatePicker::make('start_date'),
            TextInput::make('tax_number_1')
                ->required()
                ->maxLength(100),
            TextInput::make('tax_label_1')
                ->required()
                ->maxLength(10),
            TextInput::make('tax_number_2')
                ->maxLength(100),
            TextInput::make('tax_label_2')
                ->maxLength(10),
            TextInput::make('default_profit_percent')
                ->numeric()
                ->default(0)
                ->step(0.01),
            Select::make('time_zone')
                ->options([
                    'Asia/Kolkata' => 'Asia/Kolkata',
                    'UTC' => 'UTC',
                    // Add more timezone options as needed
                ])
                ->default('Asia/Kolkata'),
            Select::make('fy_start_month')
                ->options(array_combine(range(1, 12), range(1, 12)))
                ->default(1),
            Select::make('accounting_method')
                ->options([
                    'fifo' => 'FIFO',
                    'lifo' => 'LIFO',
                    'avco' => 'AVCO',
                ])
                ->default('fifo'),
            TextInput::make('default_sales_discount')
                ->numeric()
                ->step(0.01),
        ];
    }

    public function render(): View
    {
        return view('livewire.dash.business-listing-component');
    }
}