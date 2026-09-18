export default function FormErrors({ errors }) {
    return Object.keys(errors).length > 0 && <div role="alert" className="my-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{Object.entries(errors).map(([key, message]) => <p key={key}>{message}</p>)}</div>;
}
