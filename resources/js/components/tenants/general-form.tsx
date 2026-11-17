import { Form } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import TenantController from '@/actions/App/Http/Controllers/Central/TenantController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import type { TenantSummary } from '@/types';

export function GeneralForm({
    tenant,
    plans,
}: {
    tenant: TenantSummary;
    plans: string[];
}) {
    const [domains, setDomains] = useState<string[]>(tenant.domains);

    return (
        <Form
            {...TenantController.update.form(tenant.id)}
            options={{ preserveScroll: true }}
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle>Información de la empresa</CardTitle>
                            <CardDescription>
                                Identificador interno:{' '}
                                <span className="font-mono">{tenant.id}</span>
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="name">Razón social</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={tenant.name}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="nit">NIT</Label>
                                <Input
                                    id="nit"
                                    name="nit"
                                    defaultValue={tenant.nit ?? ''}
                                />
                                <InputError message={errors.nit} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="plan">Plan</Label>
                                <NativeSelect
                                    id="plan"
                                    name="plan"
                                    defaultValue={tenant.plan}
                                >
                                    {plans.map((p) => (
                                        <option key={p} value={p}>
                                            {p.charAt(0).toUpperCase() +
                                                p.slice(1)}
                                        </option>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.plan} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    Correo de contacto
                                </Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    defaultValue={tenant.email ?? ''}
                                />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="phone">Teléfono</Label>
                                <Input
                                    id="phone"
                                    name="phone"
                                    defaultValue={tenant.phone ?? ''}
                                />
                                <InputError message={errors.phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="radicado_prefix">
                                    Prefijo de radicado
                                </Label>
                                <Input
                                    id="radicado_prefix"
                                    name="radicado_prefix"
                                    defaultValue={tenant.radicado_prefix}
                                    maxLength={6}
                                    className="font-mono uppercase"
                                    required
                                />
                                <InputError message={errors.radicado_prefix} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Dominios</CardTitle>
                            <CardDescription>
                                El primero es el dominio principal. Puedes
                                agregar un dominio propio de la empresa (ej:
                                pqrs.miempresa.com) apuntándolo por DNS a este
                                servidor.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {domains.map((domain, i) => (
                                <div key={i} className="flex gap-2">
                                    <Input
                                        name="domains[]"
                                        value={domain}
                                        onChange={(e) =>
                                            setDomains(
                                                domains.map((d, j) =>
                                                    j === i
                                                        ? e.target.value
                                                        : d,
                                                ),
                                            )
                                        }
                                        className="font-mono"
                                        placeholder="pqrs.miempresa.com"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={domains.length === 1}
                                        onClick={() =>
                                            setDomains(
                                                domains.filter(
                                                    (_, j) => j !== i,
                                                ),
                                            )
                                        }
                                        aria-label="Quitar dominio"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            ))}
                            {Object.entries(errors)
                                .filter(([k]) => k.startsWith('domains'))
                                .map(([k, v]) => (
                                    <InputError key={k} message={v} />
                                ))}
                            {domains.length < 5 && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setDomains([...domains, ''])}
                                >
                                    <Plus />
                                    Agregar dominio
                                </Button>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar cambios
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
