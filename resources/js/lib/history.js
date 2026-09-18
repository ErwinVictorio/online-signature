export const initialHistory = present => ({ past: [], present, future: [] });
export function historyReducer(state, action) {
    if (action.type === 'undo' && state.past.length) return { past: state.past.slice(0, -1), present: state.past.at(-1), future: [state.present, ...state.future] };
    if (action.type === 'redo' && state.future.length) return { past: [...state.past, state.present], present: state.future[0], future: state.future.slice(1) };
    if (action.type === 'set') {
        const next = typeof action.value === 'function' ? action.value(state.present) : action.value;
        if (JSON.stringify(next) === JSON.stringify(state.present)) return state;
        return { past: [...state.past.slice(-99), state.present], present: next, future: [] };
    }
    return state;
}
