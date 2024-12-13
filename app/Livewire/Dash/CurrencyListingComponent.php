<?php

namespace App\Livewire\Dash;

use App\Models\Currency;
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

class CurrencyListingComponent extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(Currency::query())
            ->columns([
                TextColumn::make('country')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('symbol')
                    ->searchable(),
                TextColumn::make('thousand_separator')
                    ->label('1000 Separator'),
                TextColumn::make('decimal_separator')
                    ->label('Decimal Separator'),
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
                    ->form($this->getFormSchema()),
            ]);
    }

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('country')
                ->required()
                ->maxLength(100),
            TextInput::make('currency')
                ->required()
                ->maxLength(100),
            TextInput::make('code')
                ->required()
                ->maxLength(25),
            TextInput::make('symbol')
                ->required()
                ->maxLength(25),
            TextInput::make('thousand_separator')
                ->required()
                ->maxLength(10)
                ->default(','),
            TextInput::make('decimal_separator')
                ->required()
                ->maxLength(10)
                ->default('.'),
        ];
    }

    public function render(): View
    {
        return view('livewire.dash.currency-listing-component');
    }
}