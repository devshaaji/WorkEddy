function riskClass(level) {
  if (level === 'high') return 'risk-pill risk-high';
  if (level === 'moderate') return 'risk-pill risk-moderate';
  return 'risk-pill risk-low';
}

window.workEddy = { riskClass };
