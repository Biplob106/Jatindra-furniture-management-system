import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

/**
 * Each row is one thing the shop currently tracks on paper. Order follows the
 * working day: an order comes in, workers are marked, dues are recorded, the
 * cash box is counted at night.
 */
const LEDGER_ROWS = [
    {
        label: 'অর্ডার',
        detail: 'কাস্টম ফার্নিচারের অর্ডার, ডিজাইনের ছবি, কাঠ ও মাপ, ডেলিভারির তারিখ।',
    },
    {
        label: 'হাজিরা ও মজুরি',
        detail: 'দৈনিক হাজিরা এক ট্যাপে। অগ্রিম, টিফিন ও সাপ্তাহিক পেমেন্ট সরাসরি খাতায় ওঠে।',
    },
    {
        label: 'বাকি',
        detail: 'সাপ্লায়ারকে কত দিতে হবে, কাস্টমারের কাছে কত পাওনা — তারিখসহ।',
    },
    {
        label: 'দিনের ক্লোজিং',
        detail: 'দিনের আয়-ব্যয় মিলিয়ে ক্যাশ বাক্সে কত থাকার কথা, আর কত পাওয়া গেল।',
    },
];

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="যতীন্দ্র ফার্নিচার — হিসাব ব্যবস্থাপনা">
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="" />
                <link
                    href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Baloo+Da+2:wght@600;700;800&family=Anek+Bangla:wght@500;600&display=swap"
                    rel="stylesheet"
                />
            </Head>

            <div className="jt min-h-screen">
                <div className="mx-auto flex min-h-screen w-full max-w-3xl flex-col px-5 py-6 sm:px-8 sm:py-10">
                    <header className="flex items-center justify-between gap-4">
                        <span className="jt-label text-[11px] uppercase" style={{ color: 'var(--jt-muted)' }}>
                            দোকান ও কারখানা
                        </span>

                        <nav className="flex items-center gap-2">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-sm px-4 py-2 text-sm font-semibold transition-colors"
                                    style={{ backgroundColor: 'var(--jt-board)', color: 'var(--jt-bone)' }}
                                >
                                    ড্যাশবোর্ড
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="rounded-sm px-4 py-2 text-sm font-semibold transition-colors"
                                        style={{ backgroundColor: 'var(--jt-board)', color: 'var(--jt-bone)' }}
                                    >
                                        লগ ইন
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="rounded-sm border px-4 py-2 text-sm font-medium transition-colors"
                                        style={{ borderColor: 'var(--jt-rule)', color: 'var(--jt-ink)' }}
                                    >
                                        নতুন অ্যাকাউন্ট
                                    </Link>
                                </>
                            )}
                        </nav>
                    </header>

                    <main className="flex flex-1 flex-col justify-center py-12 sm:py-16">
                        {/* Signature: the shop's own signboard, painted and bolted up. */}
                        <div className="jt-board jt-anim-settle relative rounded-[3px] px-7 py-10 text-center sm:px-14 sm:py-14">
                            {[
                                'top-3 left-3',
                                'top-3 right-3',
                                'bottom-3 left-3',
                                'bottom-3 right-3',
                            ].map((position) => (
                                <span
                                    key={position}
                                    aria-hidden="true"
                                    className={`jt-bolt absolute ${position} h-2 w-2 rounded-full`}
                                />
                            ))}

                            <p
                                className="jt-label mb-3 text-[10px] uppercase sm:text-[11px]"
                                style={{ color: 'rgba(232, 179, 58, 0.75)' }}
                            >
                                প্রতিষ্ঠান
                            </p>
                            <h1
                                className="jt-display jt-painted text-[2.6rem] leading-[1.25] font-extrabold sm:text-6xl"
                                style={{ letterSpacing: '-0.01em' }}
                            >
                                যতীন্দ্র ফার্নিচার
                            </h1>
                            <p
                                className="mx-auto mt-3 max-w-md text-sm leading-[1.9] sm:text-base"
                                style={{ color: 'rgba(244, 239, 225, 0.82)' }}
                            >
                                কাঠ, নকশা, বার্নিশ ও সিএনসি — সবকিছুর হিসাব একটাই খাতায়।
                            </p>
                        </div>

                        <div className="mt-10 sm:mt-12">
                            <div className="jt-rule-brass jt-anim-draw h-px w-full" />

                            <h2 className="jt-display mt-7 text-2xl leading-[1.55] font-bold sm:text-[2rem]">
                                খাতার হিসাব, এখন ফোনে।
                            </h2>
                            <p
                                className="mt-3 max-w-xl text-[15px] leading-[1.9] sm:text-base"
                                style={{ color: 'var(--jt-muted)' }}
                            >
                                কাগজের খাতা হারায়, ভেজে, আর মাস শেষে মেলে না। এখানে যা লেখা হয় তা মুছে যায় না —
                                কে কত পাবে, কে কত নিয়েছে, দিনশেষে বাক্সে কত থাকার কথা, সব থেকে যায়।
                            </p>
                        </div>

                        {/* Ledger rows: ruled lines, because the thing being replaced is ruled lines. */}
                        <dl className="mt-9">
                            {LEDGER_ROWS.map((row, index) => (
                                <div
                                    key={row.label}
                                    className="jt-row jt-anim-rise flex flex-col gap-1 py-4 sm:flex-row sm:gap-8 sm:py-[18px]"
                                    style={{ animationDelay: `${420 + index * 70}ms` }}
                                >
                                    <dt className="flex shrink-0 items-baseline gap-2.5 sm:w-44">
                                        <span
                                            aria-hidden="true"
                                            className="h-[7px] w-[7px] shrink-0 translate-y-[-2px] rounded-full"
                                            style={{ backgroundColor: 'var(--jt-brass)' }}
                                        />
                                        <span className="text-[15px] font-semibold sm:text-base">{row.label}</span>
                                    </dt>
                                    <dd
                                        className="pl-[18px] text-[14px] leading-[1.85] sm:pl-0 sm:text-[15px]"
                                        style={{ color: 'var(--jt-muted)' }}
                                    >
                                        {row.detail}
                                    </dd>
                                </div>
                            ))}
                        </dl>

                        {!auth.user && (
                            <div className="mt-10 flex flex-col gap-3 sm:flex-row sm:items-center">
                                <Link
                                    href={route('login')}
                                    className="rounded-sm px-7 py-3.5 text-center text-base font-semibold transition-transform active:scale-[0.99]"
                                    style={{ backgroundColor: 'var(--jt-mahogany)', color: 'var(--jt-bone)' }}
                                >
                                    খাতা খুলুন
                                </Link>
                                <p className="text-sm leading-[1.8] sm:pl-2" style={{ color: 'var(--jt-muted)' }}>
                                    দোকানের দেওয়া ফোন নম্বর ও পাসওয়ার্ড দিয়ে ঢুকুন।
                                </p>
                            </div>
                        )}
                    </main>

                    <footer
                        className="jt-label flex flex-wrap items-center gap-x-3 gap-y-1 border-t pt-5 text-[10px] uppercase"
                        style={{ borderColor: 'var(--jt-rule)', color: 'var(--jt-muted)' }}
                    >
                        <span>যতীন্দ্র ফার্নিচার</span>
                        <span aria-hidden="true">·</span>
                        <span>অভ্যন্তরীণ ব্যবহারের জন্য</span>
                    </footer>
                </div>
            </div>
        </>
    );
}
