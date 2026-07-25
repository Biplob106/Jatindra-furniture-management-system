import { MasterDataFormPage } from '@/components/master-data-page';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { emptyTradeForm, TradeFormData, TradeFormFields } from './trade-form';

export default function CreateTrade() {
    const { data, setData, post, processing, errors } = useForm<TradeFormData>(emptyTradeForm);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('trades.store'));
    };

    return (
        <MasterDataFormPage title="নতুন কাজের ধরন" resource="trades" resourceTitle="কাজের ধরন" onSubmit={submit}>
            <TradeFormFields data={data} setData={setData} errors={errors} processing={processing} />
        </MasterDataFormPage>
    );
}
