import { useEffect, useRef, useState, type ReactNode } from 'react';
import { createRoot } from 'react-dom/client';

type Group = { id: string; name: string; color: string; statuses: string[] };
type Link = { from: string; to: string };
type Flow = { groups: Group[]; links: Link[] };

type MenuState =
	| { kind: 'transitions' | 'group'; groupId: string; x: number; y: number }
	| { kind: 'card'; status: string; x: number; y: number }
	| null;

const cfg = (window as any).JankxOrderFlow || {};
const STATUS_KEYS: string[] = cfg.statusKeys || [];
const STATUS_LABELS: Record<string, string> = cfg.statuses || {};

const PALETTE = [
	'#f59e0b',
	'#2563eb',
	'#7c3aed',
	'#16a34a',
	'#dc2626',
	'#64748b',
	'#0891b2',
	'#db2777',
	'#4f46e5',
	'#65a30d',
];

const LABEL = (status: string): string => STATUS_LABELS[status] || status;

function cloneFlow(flow: Flow): Flow {
	return {
		groups: flow.groups.map((g) => ({ ...g, statuses: [...g.statuses] })),
		links: flow.links.map((l) => ({ ...l })),
	};
}

function cleanFlow(flow: Flow): Flow {
	const ids = new Set(flow.groups.map((g) => g.id));
	const seen = new Set<string>();
	const groups = flow.groups.map((g) => {
		const kept: string[] = [];
		g.statuses.forEach((s) => {
			if (!seen.has(s)) {
				seen.add(s);
				kept.push(s);
			}
		});
		return { ...g, statuses: kept };
	});
	const links = flow.links.filter(
		(l) => ids.has(l.from) && ids.has(l.to) && l.from !== l.to && !seen.has(`${l.from}>${l.to}`) && (seen.add(`${l.from}>${l.to}`) || true)
	);
	return { groups, links };
}

function openAt(e: { currentTarget: EventTarget | null }): { x: number; y: number } {
	const r = (e.currentTarget as HTMLElement).getBoundingClientRect();
	return { x: Math.min(r.left, window.innerWidth - 300), y: r.bottom + 6 };
}

function useOutside(onClose: () => void) {
	const ref = useRef<HTMLDivElement | null>(null);
	useEffect(() => {
		const handler = (e: MouseEvent) => {
			if (ref.current && !ref.current.contains(e.target as Node)) {
				onClose();
			}
		};
		document.addEventListener('mousedown', handler);
		return () => document.removeEventListener('mousedown', handler);
	}, [onClose]);
	return ref;
}

function Popover({ x, y, children }: { x: number; y: number; children: ReactNode }) {
	const ref = useOutside(() => {
		const evt = new CustomEvent('jankx-flow-close-popover');
		document.dispatchEvent(evt);
	});
	return (
		<div className="jxof-popover" style={{ left: x, top: y }} ref={ref} onMouseDown={(e) => e.stopPropagation()}>
			{children}
		</div>
	);
}

function App() {
	const [flow, setFlow] = useState<Flow>(() => cleanFlow(cloneFlow((cfg.flow as Flow) || { groups: [], links: [] })));
	const [menu, setMenu] = useState<MenuState>(null);
	const [dirty, setDirty] = useState(false);
	const [saving, setSaving] = useState(false);
	const [flash, setFlash] = useState('');
	const [error, setError] = useState('');
	const [dragStatus, setDragStatus] = useState<string | null>(null);
	const [hover, setHover] = useState<string | null>(null);

	useEffect(() => {
		const handler = () => setMenu(null);
		document.addEventListener('jankx-flow-close-popover', handler);
		return () => document.removeEventListener('jankx-flow-close-popover', handler);
	}, []);

	const mutate = (fn: (draft: Flow) => void) => {
		setFlow((prev) => {
			const draft = cloneFlow(prev);
			fn(draft);
			return draft;
		});
		setDirty(true);
		setFlash('');
	};

	const groupById = (id: string) => flow.groups.find((g) => g.id === id);
	const assigned = new Set(flow.groups.flatMap((g) => g.statuses));
	const unassigned = STATUS_KEYS.filter((s) => !assigned.has(s));
	const hasLink = (from: string, to: string) => flow.links.some((l) => l.from === from && l.to === to);

	const moveStatus = (status: string, targetId: string | null, index?: number) => {
		mutate((draft) => {
			draft.groups.forEach((g) => {
				g.statuses = g.statuses.filter((s) => s !== status);
			});
			if (targetId === null) return;
			const target = draft.groups.find((g) => g.id === targetId);
			if (!target) return;
			const at = index === undefined ? target.statuses.length : Math.max(0, Math.min(index, target.statuses.length));
			target.statuses.splice(at, 0, status);
		});
	};

	const toggleLink = (from: string, to: string) => {
		mutate((draft) => {
			const exists = draft.links.some((l) => l.from === from && l.to === to);
			draft.links = exists
				? draft.links.filter((l) => !(l.from === from && l.to === to))
				: [...draft.links, { from, to }];
		});
	};

	const addGroup = () => {
		mutate((draft) => {
			const id = `g${Date.now().toString(36)}`;
			const color = PALETTE[draft.groups.length % PALETTE.length];
			draft.groups.push({ id, name: `Nhóm mới ${draft.groups.length + 1}`, color, statuses: [] });
		});
	};

	const removeGroup = (id: string) => {
		const g = groupById(id);
		if (!g) return;
		if (g.statuses.length && !window.confirm(`Xoá nhóm "${g.name}"? Các trạng thái sẽ về "Chưa phân nhóm".`)) return;
		if (!g.statuses.length && !window.confirm(`Xoá nhóm "${g.name}"?`)) return;
		mutate((draft) => {
			draft.groups = draft.groups.filter((x) => x.id !== id);
			draft.links = draft.links.filter((l) => l.from !== id && l.to !== id);
		});
	};

	const renameGroup = (id: string, name: string) => {
		mutate((draft) => {
			const g = draft.groups.find((x) => x.id === id);
			if (g && name.trim()) g.name = name.trim().slice(0, 60);
		});
	};

	const recolorGroup = (id: string, color: string) => {
		mutate((draft) => {
			const g = draft.groups.find((x) => x.id === id);
			if (g) g.color = color;
		});
	};

	const resetFlow = () => {
		if (!window.confirm('Đặt lại luồng mặc định? Mọi cấu hình hiện tại sẽ bị mất.')) return;
		mutate((draft) => {
			const defaults = cfg.defaultFlow as Flow | undefined;
			if (defaults) {
				draft.groups = cloneFlow(defaults).groups;
				draft.links = cloneFlow(defaults).links;
			}
		});
		setFlash('Đã đặt lại (nhớ bấm Lưu)');
	};

	const save = async () => {
		setSaving(true);
		setError('');
		try {
			const res = await fetch(cfg.restUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
				body: JSON.stringify({ flow: cleanFlow(flow) }),
			});
			const data = await res.json();
			if (!res.ok || !data.success) {
				throw new Error(data.message || `HTTP ${res.status}`);
			}
			setFlow(cleanFlow(cloneFlow(data.flow)));
			setDirty(false);
			setFlash('Đã lưu luồng trạng thái');
			setTimeout(() => setFlash(''), 3000);
		} catch (e: any) {
			setError(`Không lưu được: ${e.message}`);
		} finally {
			setSaving(false);
		}
	};

	const targetsOf = (groupId: string) => flow.links.filter((l) => l.from === groupId).map((l) => groupById(l.to)).filter(Boolean) as Group[];

	const renderColumn = (g: Group, key?: string, extraClass = '') => {
		const targets = targetsOf(g.id);
		return (
			<div
				key={key ?? g.id}
				className={`jxof-column ${extraClass} ${hover === g.id ? 'is-hover' : ''}`}
				onDragOver={(e) => {
					e.preventDefault();
					if (hover !== g.id) setHover(g.id);
				}}
				onDragLeave={() => setHover((h) => (h === g.id ? null : h))}
				onDrop={(e) => {
					e.preventDefault();
					const status = e.dataTransfer.getData('text/jxof-status') || dragStatus;
					setHover(null);
					if (status) moveStatus(status, g.id);
					setDragStatus(null);
				}}
			>
				<div className="jxof-column-strip" style={{ background: g.color || '#64748b' }} />
				<div className="jxof-column-header">
					<span className="jxof-column-dot" style={{ background: g.color || '#64748b' }} />
					<span className="jxof-column-name" title={g.name}>
						{g.name}
					</span>
					<span className="jxof-column-count">{g.statuses.length}</span>
					{g.id !== '__unassigned' && (
						<span className="jxof-column-actions">
						<button
							type="button"
							className="jxof-icon-btn"
							title="Chuyển tiếp cho phép (mũi tên)"
							onClick={(e) => setMenu({ kind: 'transitions', groupId: g.id, ...openAt(e) })}
						>
							⇄
						</button>
						<button
							type="button"
							className="jxof-icon-btn"
							title="Tuỳ chỉnh nhóm"
							onClick={(e) => setMenu({ kind: 'group', groupId: g.id, ...openAt(e) })}
						>
							⋯
						</button>
						</span>
					)}
				</div>
				{targets.length > 0 && (
					<div className="jxof-column-links">
						{targets.map((t) => (
							<span key={t!.id} className="jxof-link-chip" style={{ borderColor: t!.color }} title={`Cho phép chuyển sang nhóm "${t!.name}"`}>
								→ {t!.name}
							</span>
						))}
					</div>
				)}
				<div className="jxof-cards">
					{g.statuses.map((status, idx) => (
						<div
							key={status}
							draggable
							className={`jxof-card ${dragStatus === status ? 'is-dragging' : ''}`}
							onDragStart={(e) => {
								e.dataTransfer.setData('text/jxof-status', status);
								e.dataTransfer.effectAllowed = 'move';
								setDragStatus(status);
							}}
							onDragEnd={() => {
								setDragStatus(null);
								setHover(null);
							}}
							onDragOver={(e) => {
								e.preventDefault();
								e.stopPropagation();
								setHover(g.id);
							}}
							onDrop={(e) => {
								e.preventDefault();
								e.stopPropagation();
								const s = e.dataTransfer.getData('text/jxof-status') || dragStatus;
								setHover(null);
								if (s && s !== status) moveStatus(s, g.id, idx);
								setDragStatus(null);
							}}
							onClick={(e) => setMenu({ kind: 'card', status, ...openAt(e) })}
						>
							<span className="jxof-card-label">{LABEL(status)}</span>
							<span className="jxof-card-key">{status}</span>
						</div>
					))}
					{g.statuses.length === 0 && <div className="jxof-column-empty">Kéo trạng thái vào đây</div>}
				</div>
			</div>
		);
	};

	const renderPopover = () => {
		if (!menu) return null;

		if (menu.kind === 'transitions') {
			const g = groupById(menu.groupId);
			if (!g) return null;
			return (
				<Popover x={menu.x} y={menu.y}>
					<div className="jxof-pop-title">Chuyển tiếp từ “{g.name}”</div>
					<div className="jxof-pop-hint">Chọn nhóm đích được phép chuyển tới:</div>
					{flow.groups
						.filter((x) => x.id !== g.id)
						.map((target) => (
							<label key={target.id} className="jxof-pop-row">
								<input type="checkbox" checked={hasLink(g.id, target.id)} onChange={() => toggleLink(g.id, target.id)} />
								<span className="jxof-column-dot" style={{ background: target.color }} />
								<span>{target.name}</span>
								<span className="jxof-pop-count">{target.statuses.length}</span>
							</label>
						))}
					{flow.groups.length <= 1 && <div className="jxof-pop-hint">Cần ít nhất 2 nhóm để có chuyển tiếp.</div>}
				</Popover>
			);
		}

		if (menu.kind === 'group') {
			const g = groupById(menu.groupId);
			if (!g) return null;
			return (
				<Popover x={menu.x} y={menu.y}>
					<div className="jxof-pop-title">Tuỳ chỉnh nhóm</div>
					<div className="jxof-pop-row">
						<input
							type="text"
							defaultValue={g.name}
							key={g.name}
							className="jxof-pop-input"
							maxLength={60}
							onBlur={(e) => renameGroup(g.id, e.target.value)}
							onKeyDown={(e) => {
								if (e.key === 'Enter') (e.target as HTMLInputElement).blur();
							}}
							placeholder="Tên nhóm"
						/>
					</div>
					<div className="jxof-pop-colors">
						{PALETTE.map((c) => (
							<button
								key={c}
								type="button"
								className={`jxof-swatch ${g.color === c ? 'is-active' : ''}`}
								style={{ background: c }}
								title={c}
								onClick={() => recolorGroup(g.id, c)}
							/>
						))}
					</div>
					{unassigned.length > 0 && (
						<div className="jxof-pop-sub">Thêm trạng thái vào nhóm này:</div>
					)}
					{unassigned.map((s) => (
						<button key={s} type="button" className="jxof-pop-action" onClick={() => moveStatus(s, g.id)}>
							+ {LABEL(s)} <span className="jxof-card-key">{s}</span>
						</button>
					))}
					<hr className="jxof-pop-hr" />
					<button type="button" className="jxof-pop-action is-danger" onClick={() => removeGroup(g.id)}>
						Xoá nhóm
					</button>
				</Popover>
			);
		}

		// card menu
		const currentGroup = flow.groups.find((g) => g.statuses.includes(menu.status));
		return (
			<Popover x={menu.x} y={menu.y}>
				<div className="jxof-pop-title">
					{LABEL(menu.status)} <span className="jxof-card-key">{menu.status}</span>
				</div>
				<div className="jxof-pop-hint">Chuyển vào nhóm:</div>
				{flow.groups.map((g) => (
					<button
						key={g.id}
						type="button"
						className={`jxof-pop-action ${currentGroup?.id === g.id ? 'is-current' : ''}`}
						onClick={() => {
							moveStatus(menu.status, g.id);
							document.dispatchEvent(new CustomEvent('jankx-flow-close-popover'));
						}}
					>
						<span className="jxof-column-dot" style={{ background: g.color }} />
						{g.name}
						{currentGroup?.id === g.id && <span className="jxof-pop-current">hiện tại</span>}
					</button>
				))}
				{unassigned.includes(menu.status) === false && (
					<button
						type="button"
						className="jxof-pop-action"
						onClick={() => {
							moveStatus(menu.status, null);
							document.dispatchEvent(new CustomEvent('jankx-flow-close-popover'));
						}}
					>
						Chưa phân nhóm (bỏ khỏi luồng)
					</button>
				)}
			</Popover>
		);
	};

	return (
		<div className="jxof-app">
			<div className="jxof-toolbar">
				<div className="jxof-toolbar-left">
					<button type="button" className="button button-primary" onClick={save} disabled={saving || !dirty}>
						{saving ? 'Đang lưu…' : 'Lưu'}
					</button>
					<button
						type="button"
						className="button"
						disabled={!dirty}
						onClick={() => {
							setFlow(cleanFlow(cloneFlow((cfg.flow as Flow) || { groups: [], links: [] })));
							setDirty(false);
							setError('');
						}}
					>
						Hoàn tác
					</button>
					<button type="button" className="button" onClick={addGroup}>
						＋ Thêm nhóm
					</button>
					<button type="button" className="button button-link-delete" onClick={resetFlow}>
						Đặt lại mặc định
					</button>
				</div>
				<div className="jxof-toolbar-right">
					{error && <span className="jxof-msg is-error">{error}</span>}
					{flash && <span className="jxof-msg is-ok">{flash}</span>}
					{dirty && !flash && <span className="jxof-msg is-dirty">Chưa lưu thay đổi</span>}
				</div>
			</div>

			<div className="jxof-board-wrap">
				<div className="jxof-board">
					{unassigned.length > 0 &&
						renderColumn(
							{ id: '__unassigned', name: 'Chưa phân nhóm', color: '#94a3b8', statuses: unassigned },
							'__unassigned',
							'is-unassigned'
						)}
					{flow.groups.map((g) => renderColumn(g))}
					<div className="jxof-column is-add" onClick={addGroup} role="button">
						＋ Thêm nhóm
					</div>
				</div>
			</div>

			<p className="jxof-help">
				Kéo-thả trạng thái giữa các cột để xếp lại; bấm ⇄ của cột để bật/tắt các mũi tên chuyển tiếp; bấm ⋯ để đổi tên, đổi màu,
				thêm/xoá nhóm. Lưu lại để áp dụng cho trang chi tiết đơn hàng và REST huỷ đơn.
			</p>

			{renderPopover()}
		</div>
	);
}

const rootEl = document.getElementById('jankx-order-flow-app');
if (rootEl) {
	createRoot(rootEl).render(<App />);
}
