<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Barang;
use App\Models\Margin;
use App\Models\Satuan;
use App\Models\Payment;
use App\Models\Rekening;
use Filament\Forms\Form;
use App\Models\Penjualan;
use App\Models\SatuanIsi;
use App\Enums\OrderStatus;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use App\Models\PaymentMethod;
use Illuminate\Support\Carbon;
use Filament\Resources\Resource;
use Filament\Forms\Components\Toggle;
use Filament\Support\Enums\FontWeight;
use Filament\Forms\Components\Repeater;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions\Action;
use App\Filament\Resources\PenjualanResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\PenjualanResource\RelationManagers;

class PenjualanResource extends Resource
{
    protected static ?string $model = Penjualan::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Penjualan';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->schema(static::getDetailsFormSchema())
                            ->columns(3),
                        // ->columnSpan(10),
                    ])
                    ->columnSpan(['lg' => fn (?Penjualan $record) => $record === null ? 3 : 2]),

                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Di Buat ')
                            ->content(fn (Penjualan $record): ?string => $record->created_at?->diffForHumans()),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Terakhir Di Ubah ')
                            ->content(fn (Penjualan $record): ?string => $record->updated_at?->diffForHumans()),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn (?Penjualan $record) => $record === null),
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Section::make('Order Barang')
                            ->headerActions([
                                // Action::make('delete')
                                //     ->modalHeading('Apa Kamu Yakin?')
                                //     ->modalDescription('Semua Yang Berhubungan Dengan Data Ini Juga Akan Terhapus.')
                                //     ->requiresConfirmation()
                                //     ->color('danger')
                                //     ->action(fn (Forms\Set $set) => $set('items', [])),
                            ])
                            ->schema([
                                static::getItemsRepeater(),
                            ])
                    ])
                    ->columnSpan('full'),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->schema(static::getPaymentFormSchema())

                            ->columns(3),
                        // ->columnSpan(10),
                    ])
                    ->columnSpan(['lg' => fn (?Penjualan $record) => $record === null ? 3 : 2]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.nama')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Jumlah Item'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Penjualan')
                    ->date()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('items_sum_sub_total')->sum('items', 'sub_total')
                    ->label("Total")
                    ->money("IDR")
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money("IDR"),
                    ]),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Di Buat Dari')
                            ->placeholder(fn ($state): string => 'Dec 18, ' . now()->subYear()->format('Y')),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Di Buat Sampai')
                            ->placeholder(fn ($state): string => now()->format('M d, Y')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Order Dari ' . Carbon::parse($data['created_from'])->toFormattedDateString();
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Order Sampai ' . Carbon::parse($data['created_until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->groups([
                Tables\Grouping\Group::make('created_at')
                    ->label('Order Dibuat')
                    ->date()
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }
    public static function getWidgets(): array
    {
        return [
            PenjualanResource\Widgets\PenjualanStats::class,
        ];
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPenjualans::route('/'),
            'create' => Pages\CreatePenjualan::route('/create'),
            'view' => Pages\ViewPenjualan::route('/{record}'),
            'edit' => Pages\EditPenjualan::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice', 'customer.nama'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Penjualan $record */

        return [
            'Customer' => optional($record->customer)->nama,
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['customer', 'items']);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::where('status', 'baru')->count();
    }
    public static function getDetailsFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('invoice')
                ->label('No Invoice')
                ->default('Inv-' . random_int(100000, 999999))
                ->disabled()
                ->dehydrated()
                ->required()
                ->maxLength(32)
                ->columnSpan(1)
                ->unique(Penjualan::class, 'invoice', ignoreRecord: true),

            Forms\Components\Select::make('customer_id')
                ->relationship('customer', 'nama')
                ->searchable()
                ->preload()
                ->required()
                ->columnSpan(1)
                ->createOptionForm([
                    Forms\Components\TextInput::make('nama')
                        ->required()
                        ->maxLength(50),
                    Forms\Components\TextInput::make('alamat')
                        ->required(),
                    Forms\Components\TextInput::make('kota')
                        ->required(),
                    Forms\Components\TextInput::make('provinsi')
                        ->required(),
                    Forms\Components\TextInput::make('kode_pos')
                        ->required(),
                    Forms\Components\TextInput::make('kontak')
                        ->required(),
                ])
                ->createOptionAction(function (Action $action) {
                    return $action
                        ->modalHeading('Customer Baru')
                        ->modalButton('Customer Baru')
                        ->modalWidth('lg');
                }),

            Forms\Components\Select::make('status')
                ->options(OrderStatus::class)
                ->required()
                ->columnSpan(1)
                ->native(false),
            Forms\Components\MarkdownEditor::make('notes')
                ->columnSpan('full'),
        ];
    }
    public static function getItemsRepeater(): Repeater
    {
        return Repeater::make('items')
            ->relationship()
            ->live()
            ->schema([
                Forms\Components\Select::make('barang_id')
                    ->label('Barang')
                    ->options(fn (Forms\Get $get) => Barang::query()->pluck('nama', 'id')) //
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        $harga = Barang::find($state)?->harga_dasar ?? 0;
                        $margin = Margin::first();
                        $hitung = ($margin->margin / 100) * $harga;
                        $harga_final = $harga + $hitung;
                        $set('harga', $harga_final);
                    })
                    // ->distinct() // pake ini supaya ga bisa duplicated barang nya di validation
                    // ->fixIndistinctState()
                    // ->disableOptionsWhenSelectedInSiblingRepeaterItems() // pake ini supaya ga bisa duplicated barang nya di UI 
                    ->columnSpan([
                        'md' => 3,
                    ])
                    ->searchable(),
                Forms\Components\Select::make('satuan_isi_id')
                    ->label('Satuan')
                    // ->multiple()
                    ->options(function (Forms\Get $get) {
                        $satuan_isi = SatuanIsi::where('barang_id', $get('barang_id'))->with('satuan')->get();
                        // dd($satuan_isi);
                        return $satuan_isi->pluck('satuan.nama', 'id');
                    })
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('isi', SatuanIsi::find($state)?->isi ?? 0))
                    ->columnSpan([
                        'md' => 1,
                    ]),
                Forms\Components\TextInput::make('qty')
                    ->label('Qty')
                    ->numeric()
                    ->default(1)
                    ->live(onBlur: true)

                    ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => $set('sub_total', ($state * $get('isi') * $get('harga'))))
                    ->columnSpan([
                        'md' => 1,
                    ])
                    ->required(),
                Forms\Components\TextInput::make('harga')
                    ->label('Harga')
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->live(onBlur: true)
                    ->dehydrated()
                    ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => $set('sub_total', ($get('qty') * $get('isi') * $state)))
                    ->columnSpan([
                        'md' => 2,
                    ]),
                Forms\Components\TextInput::make('isi')
                    ->label('Isi')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (TextInput $component,  Forms\Get $get) {
                        $isi = SatuanIsi::find($get('satuan_isi_id'))?->isi;
                        $component->state($isi);
                    })
                    // ->hiddenOn(['view'])
                    ->default(0)
                    ->columnSpan([
                        'md' => 1,
                    ])
                    ->required(),
                Forms\Components\TextInput::make('sub_total')
                    ->label('Sub Total')
                    ->disabled()
                    ->prefix('Rp')
                    ->numeric()
                    ->required()
                    ->columnSpan([
                        'md' => 2,
                    ]),
                Forms\Components\Hidden::make('sub_total'),
                // Forms\Components\Hidden::make('harga')
            ])
            ->extraItemActions([
                Action::make('Lihat Barang')
                    ->tooltip('Lihat Barang')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(function (array $arguments, Repeater $component): ?string {
                        $itemData = $component->getRawItemState($arguments['item']);

                        $product = Barang::find($itemData['barang_id']);

                        if (!$product) {
                            return null;
                        }

                        return BarangResource::getUrl('edit', ['record' => $product]);
                    }, shouldOpenInNewTab: true)
                    ->hidden(fn (array $arguments, Repeater $component): bool => blank($component->getRawItemState($arguments['item'])['barang_id'])),
            ])
            // ->orderColumn('sort')
            // ->defaultItems(3)
            // ->hiddenLabel()
            ->columns([
                'md' => 10,
            ])
            ->collapsible()
            ->required();
    }

    public static function getPaymentFormSchema(): array
    {
        return [
            Forms\Components\Group::make()
                ->label('Tagihan')
                ->schema([
                    Repeater::make('payments')
                        ->relationship()
                        ->label('')
                        ->reorderable(false)
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data, Forms\Get $get) {
                            $data['total_tagihan'] =  collect($get('items'))->pluck('sub_total')->sum();
                            return $data;
                        })
                        ->schema([
                            Forms\Components\Section::make('Payment')
                                ->description('Pilih Salah 1 Dari Tipe Pembayaran Di Bawah')
                                ->collapsible()
                                ->columns(2)
                                ->schema([
                                    Forms\Components\TextInput::make('kode_payment')
                                        ->default('PAY/' . random_int(0, 999) . "/" . date('d/m/y'))
                                        ->disabled()
                                        ->dehydrated()
                                        ->maxLength(255)
                                        ->unique(Payment::class, 'kode_payment', ignoreRecord: true),
                                    Forms\Components\Select::make('payment_method_id')
                                        ->label('Pilih Method Pembayaran')
                                        ->relationship('payments.paymentMethod', 'nama')
                                        ->options(PaymentMethod::all()->pluck('nama', 'id'))
                                        ->required()
                                        ->createOptionForm([
                                            Forms\Components\TextInput::make('nama')
                                                ->required(),
                                            Forms\Components\TextInput::make('deskripsi')
                                        ]),
                                ]),
                            Forms\Components\Section::make('Tipe Pembayaran')
                                // ->relationship('payment_total')
                                ->description('Pilih Salah 1 Dari Tipe Pembayaran Di Bawah')
                                ->collapsible()
                                ->schema([
                                    // Forms\Components\Section::make('Tunai')
                                    //     ->icon('heroicon-m-banknotes')
                                    //     ->collapsible()
                                    //     ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                                    //         $data['rekening_id'] =  1;
                                    //         return $data;
                                    //     })
                                    //     ->schema([
                                    //         Forms\Components\TextInput::make('nominal')
                                    //             ->prefix('Rp '),
                                    //         Forms\Components\Section::make('Uang Muka')
                                    //             ->description('Nganjuk Ae Bang Bayar Cash Dong')
                                    //             ->collapsible()
                                    //             ->schema([
                                    //                 Toggle::make('uangmuka')
                                    //             ])
                                    //             ->columns(1),
                                    //     ])
                                    //     ->columns(1),
                                    // Forms\Components\Section::make('Transfer')
                                    //     ->relationship('paymentTf')
                                    //     ->icon('heroicon-m-arrows-right-left')
                                    //     ->collapsible()
                                    //     ->schema([
                                    //         Forms\Components\TextInput::make('nominal')
                                    //             ->label('Nominal')
                                    //             ->numeric()
                                    //             ->prefix("Rp")
                                    //             ->maxLength(255),
                                    //         Forms\Components\Select::make('rekening_id')
                                    //             ->label('Ke Rekening (Bank)')
                                    //             ->options(Rekening::all()->pluck('nama', 'id')),
                                    //         Forms\Components\TextInput::make('atas_nama')
                                    //             ->maxLength(255)
                                    //             ->columnSpanFull(),
                                    //         Forms\Components\TextInput::make('dari_bank')
                                    //             ->maxLength(255),
                                    //         Forms\Components\TextInput::make('no_rek_dari_bank')
                                    //             ->maxLength(255)
                                    //     ])
                                    //     ->columns(2),
                                    // // Forms\Components\Section::make('Giro')
                                    //     ->relationship('gyro')
                                    //     ->icon('heroicon-m-arrows-right-left')
                                    //     ->collapsible()
                                    //     ->schema([
                                    //         Forms\Components\TextInput::make('nominal_giro')
                                    //             ->label('Nominal')
                                    //             ->numeric()
                                    //             ->prefix("Rp")
                                    //             ->maxLength(255),

                                    //         Forms\Components\TextInput::make('atas_nama')
                                    //             ->maxLength(255)
                                    //             ->columnSpanFull(),
                                    //         Forms\Components\TextInput::make('dari_bank')
                                    //             ->maxLength(255),
                                    //         Forms\Components\TextInput::make('no_rek_dari_bank')
                                    //             ->label('No Giro')
                                    //             ->maxLength(255)
                                    //     ])
                                    //     ->columns(2),
                                    // Forms\Components\Section::make('Piutang Customer')
                                    //     ->relationship('pitungan')
                                    //     ->icon('heroicon-m-user-circle')
                                    //     ->collapsible()
                                    //     ->schema([
                                    //         Forms\Components\TextInput::make('nominal_jaminan')
                                    //             ->numeric()
                                    //             ->prefix("Rp")
                                    //             ->maxLength(255),
                                    //         Forms\Components\Select::make('rekening_id_customer')
                                    //             ->label('Ke Rekening (Bank)')
                                    //             ->options(Rekening::all()->pluck('nama', 'id')),
                                    //         Forms\Components\TextInput::make('atas_nama')
                                    //             ->maxLength(255)
                                    //             ->columnSpanFull(),
                                    //     ])
                                    //     ->columns(2),
                                ]),
                        ]),
                ])
                ->columnSpan(['lg' => 2]),
            Forms\Components\Group::make()
                ->schema([
                    Forms\Components\Section::make('Tagihan')
                        ->collapsible()
                        ->columns(2)
                        ->schema([
                            Forms\Components\Group::make()
                                ->columnSpan(['lg' => 1])
                                ->schema([
                                    Placeholder::make('tt')
                                        ->label('Total Tagihan')
                                        ->extraAttributes(['class' => 'font-bold'])
                                        ->content(function ($get) {
                                            return 'Rp ' . number_format(collect($get('items'))
                                                ->pluck('sub_total')
                                                ->sum(), 2);
                                        }),
                                    Placeholder::make('Uang Muka')
                                        ->content("Rp 200,000.00"),
                                    Placeholder::make('Sisa Tagihan')
                                        ->content("Rp 200,000.00")
                                ]),
                            Forms\Components\Group::make()
                                ->columnSpan(['lg' => 1])
                                ->schema([
                                    Placeholder::make('Total Di Bayar')
                                        ->content("Rp 200,000.00"),
                                    Placeholder::make('Kembalian')
                                        ->content("Rp 200,000.00"),
                                ]) //fn (Post $record): string => $record->created_at->toFormattedDateString()
                        ]),
                ])
                ->columnSpan(['lg' => 1]),
        ];
    }
}
