<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\CustomerType;
use App\Enums\WageType;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards the two things that are silently wrong when they break: money read
 * back as a float, and a soft delete that actually removed the row.
 */
class MasterDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_money_columns_keep_two_decimal_places_as_strings()
    {
        $employee = Employee::factory()->create([
            'daily_rate' => 812.5,
            'opening_advance' => 1000,
        ]);

        $fresh = $employee->fresh();

        $this->assertSame('812.50', $fresh->daily_rate);
        $this->assertSame('1000.00', $fresh->opening_advance);
    }

    public function test_shop_rent_and_account_balances_cast_to_decimal_strings()
    {
        $shop = Shop::factory()->create(['monthly_rent' => 15000]);
        $account = Account::factory()->create(['opening_balance' => 250.75]);

        $this->assertSame('15000.00', $shop->fresh()->monthly_rent);
        $this->assertSame('250.75', $account->fresh()->opening_balance);
    }

    public function test_enum_columns_cast_to_backed_enums()
    {
        $employee = Employee::factory()->piece()->create();
        $customer = Customer::factory()->create(['customer_type' => CustomerType::Dealer]);
        $account = Account::factory()->create(['type' => AccountType::MobileBanking]);

        $this->assertSame(WageType::Piece, $employee->fresh()->wage_type);
        $this->assertSame(CustomerType::Dealer, $customer->fresh()->customer_type);
        $this->assertSame(AccountType::MobileBanking, $account->fresh()->type);
    }

    public function test_every_enum_case_has_a_bengali_label()
    {
        $cases = [...WageType::cases(), ...CustomerType::cases(), ...AccountType::cases()];

        foreach ($cases as $case) {
            $this->assertNotSame('', $case->label(), $case->name.' has no label');
            $this->assertMatchesRegularExpression('/\p{Bengali}/u', $case->label(), $case->name.' label is not Bengali');
        }
    }

    /**
     * @return array<string, array<class-string>>
     */
    public static function masterDataModels(): array
    {
        return [
            'shop' => [Shop::class],
            'customer' => [Customer::class],
            'trade' => [Trade::class],
            'employee' => [Employee::class],
            'account' => [Account::class],
            'expense category' => [ExpenseCategory::class],
            'product category' => [ProductCategory::class],
        ];
    }

    #[DataProvider('masterDataModels')]
    public function test_master_data_soft_deletes_instead_of_removing_the_row(string $model)
    {
        $record = $model::factory()->create();

        $record->delete();

        $this->assertSoftDeleted($record);
        $this->assertNull($model::find($record->id));
        $this->assertNotNull($model::withTrashed()->find($record->id));
    }
}
