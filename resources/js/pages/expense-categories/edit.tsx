import { MasterDataFormPage } from '@/components/master-data-page';
import type { ExpenseCategory } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { ExpenseCategoryFormData, ExpenseCategoryFormFields } from './expense-category-form';

interface Props {
    category: ExpenseCategory;
}

export default function EditExpenseCategory({ category }: Props) {
    const { data, setData, put, processing, errors } = useForm<ExpenseCategoryFormData>({
        name: category.name,
        is_recurring: category.is_recurring,
        is_active: category.is_active,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('expense-categories.update', category.id));
    };

    return (
        <MasterDataFormPage title={category.name} resource="expense-categories" resourceTitle="খরচের খাত" onSubmit={submit}>
            <ExpenseCategoryFormFields data={data} setData={setData} errors={errors} processing={processing} />
        </MasterDataFormPage>
    );
}
