import { MasterDataFormPage } from '@/components/master-data-page';
import type { Trade } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { TradeFormData, TradeFormFields } from './trade-form';

interface Props {
    trade: Trade;
}

export default function EditTrade({ trade }: Props) {
    const { data, setData, put, processing, errors } = useForm<TradeFormData>({
        name: trade.name,
        default_daily_rate: trade.default_daily_rate,
        is_active: trade.is_active,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('trades.update', trade.id));
    };

    return (
        <MasterDataFormPage title={trade.name} resource="trades" resourceTitle="কাজের ধরন" onSubmit={submit}>
            <TradeFormFields data={data} setData={setData} errors={errors} processing={processing} />
        </MasterDataFormPage>
    );
}
