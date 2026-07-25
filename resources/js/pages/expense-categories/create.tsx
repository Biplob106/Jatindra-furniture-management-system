import { MasterDataFormPage } from '@/components/master-data-page';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { emptyExpenseCategoryForm, ExpenseCategoryFormData, ExpenseCategoryFormFields } from './expense-category-form';

export default function CreateExpenseCategory() {
    const { data, setData, post, processing, errors } = useForm<ExpenseCategoryFormData>(emptyExpenseCategoryForm);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('expense-categories.store'));
    };

    return (
        <MasterDataFormPage title="নতুন খরচের খাত" resource="expense-categories" resourceTitle="খরচের খাত" onSubmit={submit}>
            <ExpenseCategoryFormFields data={data} setData={setData} errors={errors} processing={processing} />
        </MasterDataFormPage>
    );
}
