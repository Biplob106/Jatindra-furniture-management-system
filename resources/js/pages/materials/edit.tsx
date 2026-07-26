import { toBengaliDigits } from '@/components/data-table';
import { Option } from '@/components/form-field';
import { MasterDataFormPage } from '@/components/master-data-page';
import { materialUnitLabels } from '@/types/enums';
import type { Material } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { MaterialFormData, MaterialFormFields } from './material-form';

interface Props {
    material: Material;
    categories: Option[];
    units: Option[];
}

export default function EditMaterial({ material, categories, units }: Props) {
    const { data, setData, put, processing, errors } = useForm<MaterialFormData>({
        name: material.name,
        category: material.category,
        unit: material.unit,
        min_stock: material.min_stock,
        opening_stock: material.current_stock,
        opening_cost: material.avg_cost,
        is_active: material.is_active ? '1' : '0',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('materials.update', material.id));
    };

    return (
        <MasterDataFormPage title={material.name} resource="materials" resourceTitle="মালামাল" onSubmit={submit}>
            {/* Stock is what the movements add up to, so it is shown, not edited. */}
            <div className="bg-muted/50 flex gap-6 rounded-lg border p-4">
                <div>
                    <p className="text-muted-foreground text-sm">এখন মজুদ</p>
                    <p className="text-xl font-semibold">
                        {toBengaliDigits(material.current_stock)} {materialUnitLabels[material.unit]}
                    </p>
                </div>
                <div>
                    <p className="text-muted-foreground text-sm">গড় দর</p>
                    <p className="text-xl font-semibold">৳ {toBengaliDigits(material.avg_cost)}</p>
                </div>
            </div>

            <MaterialFormFields
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                categories={categories}
                units={units}
                showOpeningStock={false}
            />
        </MasterDataFormPage>
    );
}
