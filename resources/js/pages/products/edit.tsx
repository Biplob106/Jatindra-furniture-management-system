import { toBengaliDigits } from '@/components/data-table';
import { Option } from '@/components/form-field';
import { MasterDataFormPage } from '@/components/master-data-page';
import { Money } from '@/components/money';
import type { Product } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { ProductFormData, ProductFormFields } from './product-form';

interface Props {
    product: Product;
    categories: Option[];
    shops: Option[];
}

export default function EditProduct({ product, categories, shops }: Props) {
    const { data, setData, put, processing, errors } = useForm<ProductFormData>({
        sku: product.sku,
        name: product.name,
        category_id: product.category_id ? String(product.category_id) : '',
        description: product.description ?? '',
        wood_type: product.wood_type ?? '',
        size_label: product.size_label ?? '',
        cost_price: product.cost_price,
        sale_price: product.sale_price,
        min_stock: product.min_stock,
        opening_stock: product.current_stock,
        shop_id: product.shop_id ? String(product.shop_id) : '',
        is_active: product.is_active ? '1' : '0',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('products.update', product.id));
    };

    return (
        <MasterDataFormPage title={product.name} resource="products" resourceTitle="পণ্য" onSubmit={submit}>
            {/* Stock is what the movements add up to, so it is shown, not edited. */}
            <div className="bg-muted/50 flex gap-6 rounded-lg border p-4">
                <div>
                    <p className="text-muted-foreground text-sm">দোকানে আছে</p>
                    <p className="text-xl font-semibold">{toBengaliDigits(product.current_stock)} টি</p>
                </div>
                <div>
                    <p className="text-muted-foreground text-sm">খরচ দরে মূল্য</p>
                    <p className="text-xl font-semibold">
                        <Money amount={(Number(product.current_stock) * Number(product.cost_price)).toFixed(2)} />
                    </p>
                </div>
            </div>

            <ProductFormFields
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                categories={categories}
                shops={shops}
                showOpeningStock={false}
            />
        </MasterDataFormPage>
    );
}
