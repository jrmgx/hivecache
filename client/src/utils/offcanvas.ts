/**
 * Utility function to close any visible Bootstrap offcanvas
 */
export const closeOffcanvas = () => {
  const offcanvasElement = document.querySelector('.offcanvas.show, .offcanvas-md.show') as HTMLElement;
  const bootstrap = (window as any).bootstrap;
  if (offcanvasElement && bootstrap && bootstrap.Offcanvas) {
    const offcanvasInstance = bootstrap.Offcanvas.getInstance(offcanvasElement);
    if (offcanvasInstance) {
      offcanvasInstance.hide();
      return;
    }
  }

  if (offcanvasElement) {
    const dismissButton = offcanvasElement.querySelector('[data-bs-dismiss="offcanvas"]') as HTMLElement;
    if (dismissButton) {
      dismissButton.click();
    }
  }
};

